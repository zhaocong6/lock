<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-4-3
 * Time: 下午9:13
 */

namespace Lock\Lock;

use Lock\LockInterface;
use Lock\LockException;
use Predis\Client;

class RedisLock implements LockInterface
{
    /**
     * 缓存redis
     * @var resource
     */
    private $redis;

    /**
     * 队列锁最大进程进程数量
     * @var int
     */
    private $max_queue_process = 100;

    /**
     * 抢占锁名称
     * @var string
     */
    private $lock_name;

    /**
     * 队列锁名称
     * @var string
     */
    private $queue_lock_name;

    /**
     * 队列锁进程数名称
     * @var string
     */
    private $queue_lock_process_name;

    /**
     * 等待list名称
     * @var string
     */
    private $queue_lock_list_name;

    /**
     * 锁值
     * @var string
     */
    private $lock_val;


    /**
     * 是否删除等待锁进程
     * @var bool
     */
    private $is_del_queue_lock_process = false;

    /**
     * 是否删除等待锁
     * @var bool
     */
    private $is_del_queue_lock = false;

    /**
     * 是否删除锁
     * @var bool
     */
    private $is_del_lock = false;

    /**
     * 随机数
     * @var string
     */
    private $rand_num;

    /**
     * 队列锁前缀
     * @var string
     */
    private $queue_lock_prefix = 'queue:lock';

    /**
     * 抢占锁前缀
     * @var string
     */
    private $lock_prefix = 'lock';

    /**
     * timeout
     * @var int
     */
    private $expiration = 5;

    /**
     * RedisLock constructor.
     * @param $config
     * @param $params
     */
    public function __construct($config = [], $params = [])
    {
        ignore_user_abort(true);

        $this->shutdown();
        $this->initRedis($config);
        $this->initParams($params);
    }

    /**
     * 抢占锁 (适用于限制单个用户行为)
     * 此锁不会等待, 第一个锁用户没有处理完成, 第二个用户将被拒绝
     * @param $closure
     * @param $lock_val
     * @throws \Exception
     * @return mixed
     */
    public function lock($closure, $lock_val)
    {
        $this->is_del_lock                  = true;
        $this->is_del_queue_lock_process    = false;
        $this->is_del_queue_lock            = false;

        $this->lock_val = $lock_val;
        $lock_name = $this->setLockName();
        $rand_num  = $this->randNum();

        if ($this->redis->set($lock_name, $rand_num, 'nx', 'ex', $this->expiration)) {

            try{
                $closure_res = $closure($this->redis);
            }catch (\Error $exception){
                $this->forcedShutdown();
            }

            $this->delLock();
            return $closure_res;
        }else{
            throw new LockException('操作频繁, 被服务器拒绝!', 403);
        }
    }

    /**
     * 队列锁
     * 此锁会等待, 第一个锁用户没有处理完成, 第二个用户将等待
     * @param $closure
     * @param $lock_val
     * @param int $max_queue_process   最大等待进程数
     * @param int $timeout  队列等待过期时间
     * @throws \Exception
     * @return mixed
     */
    public function queueLock($closure, $lock_val, $max_queue_process = null, $timeout = 6)
    {
        $this->is_del_lock                  = false;
        $this->is_del_queue_lock_process    = true;
        $this->is_del_queue_lock            = true;

        $this->lock_val = $lock_val;
        $queue_lock_name = $this->setQueueLockName();

        $rand_num = $this->randNum();

        $this->initQueueLockProcess();

        $queue_lock_list_name = $this->initQueueLockList();

        $this->addQueueLockProcess($max_queue_process);

        loop:
        $wait = $this->redis->blpop($queue_lock_list_name, $timeout);
        if (is_null($wait)) throw new LockException('等待超时!', 504);

        if ($this->redis->set($queue_lock_name, $rand_num, 'nx', 'ex', $this->expiration)) {

            try{
                $closure_res = $closure($this->redis);
            }catch (\Error $exception){
                $this->forcedShutdown();
            }

            $this->delQueueLockProcess();
            $this->delQueueLock();
            $this->addQueueLockList();
            return $closure_res;
        }else{
            goto loop;
        }
    }

    /**
     * 设置等待进程名称
     * @return string
     */
    private function setQueueLockProcessName()
    {
        return $this->queue_lock_process_name = $this->queue_lock_prefix. ':process:' .$this->lock_val;
    }

    /**
     * 设置等待队列
     * @return string
     */
    private function setQueueLockListName()
    {
        return $this->queue_lock_list_name = $this->queue_lock_prefix. ':list:' .$this->lock_val;
    }

    /**
     * 设置lock名称
     * @return string
     */
    private function setLockName()
    {
        return $this->lock_name = $this->lock_prefix. ':' .$this->lock_val;
    }

    /**
     * 设置队列锁名称
     * @return string
     */
    private function setQueueLockName()
    {
        return $this->queue_lock_name = $this->queue_lock_prefix. ':' .$this->lock_val;
    }

    /**
     * 初始化等待锁的进程数量
     */
    private function initQueueLockProcess()
    {
        $queue_lock_process_name = $this->setQueueLockProcessName();
        $this->redis->setnx($queue_lock_process_name, 0);
    }

    /**
     * 初始化lock list
     */
    private function initQueueLockList()
    {

        $queue_lock_list_name = $this->setQueueLockListName();

        $lua = <<<LUA
            local queueLockProcessName = KEYS[1]
            local queueLockListName    = KEYS[2]
            local expiration           = ARGV[1]
            local lockProcessNum       = tonumber(redis.call('get', queueLockProcessName))
            local queueLockListLen     = redis.call('llen', queueLockListName)
            
            if(lockProcessNum == 0 and queueLockListLen == 0)
            then
                redis.call('lpush', queueLockListName, 1)
                redis.call('expire', queueLockListName, expiration)
            end
LUA;

        $this->redis->eval($lua, 2, $this->queue_lock_process_name, $queue_lock_list_name, $this->expiration);
        return $queue_lock_list_name;
    }

    /**
     * 新增等待进程
     * @param $max_queue_process
     * @throws LockException
     */
    private function addQueueLockProcess($max_queue_process)
    {
        if ($max_queue_process) $this->max_queue_process = $max_queue_process;

        $lua = <<<LUA
            local queueLockProcessName   = KEYS[1]
            local maxQueueProcess        = tonumber(ARGV[1])
            local expiration             = tonumber(ARGV[2])
            local currentQueueProcessNum = tonumber(redis.call('get', queueLockProcessName))
     
            if(currentQueueProcessNum == maxQueueProcess)
            then
                return false
            else
                redis.call('incr', queueLockProcessName)
                redis.call('expire', queueLockProcessName, expiration)
                return true
            end
LUA;

        if (!$this->redis->eval($lua, 1, $this->queue_lock_process_name, $this->max_queue_process, $this->expiration)){
            $this->is_del_queue_lock_process = false;
            throw new LockException('操作频繁, 被服务器拒绝!', 403);
        }
    }

    /**
     * 新增等待队列list
     */
    private function addQueueLockList()
    {
        $this->redis->lpush($this->queue_lock_list_name, true);
        $this->redis->expire($this->queue_lock_list_name, $this->expiration);
    }

    /**
     * 删除当前等待进程
     */
    private function delQueueLockProcess()
    {
        if ($this->is_del_queue_lock_process){

            $lua = <<<LUA
            local queueLockProcessName   = KEYS[1]
            local currentQueueProcessNum = tonumber(redis.call('get', queueLockProcessName))
     
            if(currentQueueProcessNum > 0)
            then
                redis.call('decr', queueLockProcessName)
            end
LUA;
            $this->redis->eval($lua, 1, $this->queue_lock_process_name);
            $this->is_del_queue_lock_process = false;
        }
    }


    /**
     * 删除占用锁
     */
    private function delLock()
    {
        if ($this->is_del_lock && $this->lock_name){
            $this->redis->eval($this->delLockLua(), 2, $this->lock_name, $this->rand_num);
            $this->is_del_lock = false;
        }
    }

    /**
     * 删除队列锁
     */
    private function delQueueLock()
    {
        if ($this->is_del_queue_lock && $this->queue_lock_name){
            $this->redis->eval($this->delLockLua(), 2, $this->queue_lock_name, $this->rand_num);
            $this->is_del_queue_lock = false;
        }
    }

    /**
     * 生成随机函数
     */
    private function randNum()
    {
        return $this->rand_num = uniqid() . mt_rand(1, 1000000);
    }

    /**
     * @return string
     */
    private function delLockLua()
    {
        return <<<LUA
                local lockName = KEYS[1]
                local randNum  = KEYS[2]
                
                if(randNum == redis.call('get', lockName))
                then
                    redis.call('del', lockName)
                end
LUA;
    }

    /**
     * 初始化redis
     * @param $config
     */
    private function initRedis($config)
    {
        $config = empty($config) ? [] : $config;

        $config = array_merge([
            'read_write_timeout'=>  0,
            'persistent'        =>  true
        ], $config);

        $this->redis = new Client($config);
    }

    /**
     * 初始化参数
     * @param $params
     */
    private function initParams($params)
    {
        if (!empty($params)){
            foreach ($params as $key => $item){
                $this->$key = $item;
            }
        }
    }

    /**
     * 防止php致命错误
     */
    private function shutdown()
    {
        register_shutdown_function(function (){
            $this->forcedShutdown();
        });
    }

    /**
     * 强制关闭
     */
    private function forcedShutdown()
    {
        $this->delLock();
        $this->delQueueLock();
        $this->delQueueLockProcess();
    }

    /**
     * 删除锁
     * 防止程序中止后没解锁
     */
    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        $this->forcedShutdown();
    }
}
