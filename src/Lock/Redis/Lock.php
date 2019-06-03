<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-4-3
 * Time: 下午9:13
 */

namespace Lock\Lock\Redis;

use Lock\LockInterface;
use Lock\LockException;
use Predis\Client;

class Lock implements LockInterface
{
    //异常代码
    const PARAMS_ERROR_CODE = 400;
    const TOO_MANY_REQUESTS_ERROR_CODE = 429;
    const TIME_OUT_ERROR_CODE = 504;

    //异常信息
    const TIME_OUT_ERROR_MSG = '等待超时';
    const EXISTED_ERROR_MSG = 'lock_val重复';
    const TOO_MANY_REQUESTS_ERROR_MSG = '访问频繁';

    /**
     * 缓存redis
     * @var resource
     */
    private $redis;

    /**
     * lock 数据对象
     * @var array
     */
    private $locks = [];

    /**
     * keys
     * @var array
     */
    private $lock_keys = [];

    /**
     * queueLock 数据对象
     * @var array
     */
    private $queue_locks = [];

    /**
     * keys
     * @var array
     */
    private $queue_lock_keys = [];

    /**
     * RedisLock constructor.
     * @param $config
     * @param $params
     */
    public function __construct($config = [], $params = [])
    {
        $this->shutdown();
        $this->bootSignals();
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
        $this->pushLockKey($lock_val);

        list($current_data, $current_index) = $this->bootLock($lock_val);

        if ($this->redis->set($current_data->lock_name, $current_data->rand_num, 'nx', 'ex', $current_data->expiration)) {

            try{
                $closure_res = $closure($this->redis);

                $this->delLock($current_data);

                unset($this->locks[$current_index]);

                return $closure_res;
            }catch (\Error $exception){
                $this->forcedShutdown();
                throw $exception;
            }catch (\Exception $exception){
                $this->forcedShutdown();
                throw $exception;
            }

        }else{
            throw new LockException(self::TOO_MANY_REQUESTS_ERROR_MSG, self::TOO_MANY_REQUESTS_ERROR_CODE);
        }
    }

    /**
     * 多参数抢占锁
     * @param $closure
     * @param $lock_vals
     * @return mixed
     */
    public function locks($closure, $lock_vals)
    {
        $one_lock_val = array_pop($lock_vals);
        $one_closure = function ()use ($closure, $one_lock_val){
            return $this->lock($closure, $one_lock_val);
        };

        $go = empty($lock_vals)
            ? $one_closure
            : array_reduce($lock_vals, function ($next, $lock_val)use ($one_closure){
                return function ()use ($next, $lock_val, $one_closure){
                    return is_null($next)
                        ? $this->lock($one_closure, $lock_val)
                        : $this->lock($next, $lock_val);
                };
            });

        return $go();
    }

    /**
     * 队列锁
     * 此锁会等待, 第一个锁用户没有处理完成, 第二个用户将等待
     * @param $closure
     * @param $lock_val
     * @param int $max_queue_process   最大等待进程数
     * @param int $wait_timeout  队列等待过期时间
     * @throws \Exception
     * @return mixed
     */
    public function queueLock($closure, $lock_val, $max_queue_process = null, $wait_timeout = 6)
    {
        $this->pushQueueLockKey($lock_val);

        list($current_data, $current_index) = $this->bootQueueLock($lock_val, $max_queue_process, $wait_timeout);

        $this->initQueueLockProcess($current_data);

        $queue_lock_list_name = $this->initQueueLockList($current_data);

        $this->addQueueLockProcess($current_data);

        loop:
        $wait = $this->redis->blpop($queue_lock_list_name, $wait_timeout);
        if (is_null($wait)) throw new LockException(self::TIME_OUT_ERROR_MSG, self::TIME_OUT_ERROR_CODE);

        if ($this->redis->set($current_data->queue_lock_name, $current_data->rand_num, 'nx', 'ex', $current_data->expiration)) {

            try{
                $closure_res = $closure($this->redis);

                $this->delQueueLockProcess($current_data);

                $this->delQueueLock($current_data);

                $this->addQueueLockList($current_data);

                unset($this->queue_locks[$current_index]);

                return $closure_res;
            }catch (\Error $exception){
                $this->forcedShutdown();
                throw $exception;
            }catch (\Exception $exception){
                $this->forcedShutdown();
                throw $exception;
            }

        }else{
            goto loop;
        }
    }

    /**
     * 多参数队列锁
     * @param $closure
     * @param $lock_vals
     * @param null $max_queue_process
     * @param int $wait_timeout
     * @return mixed
     */
    public function queueLocks($closure, $lock_vals, $max_queue_process = null, $wait_timeout = 6)
    {
        $one_lock_val = array_pop($lock_vals);
        $one_closure = function ()use ($closure, $one_lock_val, $max_queue_process, $wait_timeout){
            return $this->queueLock($closure, $one_lock_val, $max_queue_process, $wait_timeout);
        };

        $go = empty($lock_vals)
            ? $one_closure
            : array_reduce($lock_vals, function ($next, $lock_val)use ($one_closure, $max_queue_process, $wait_timeout){
                return function ()use ($next, $lock_val, $one_closure, $max_queue_process, $wait_timeout){
                    return is_null($next)
                        ? $this->queueLock($one_closure, $lock_val, $max_queue_process, $wait_timeout)
                        : $this->queueLock($next, $lock_val, $max_queue_process, $wait_timeout);
                };
            });
        return $go();
    }

    /**
     * 简单限流
     * 作者地址: https://juejin.im/book/5afc2e5f6fb9a07a9b362527/section/5b4477416fb9a04fa259c496
     * @param $key
     * @param $period
     * @param $max_count
     * @return bool
     */
    public function isActionAllowed($key, $period, $max_count)
    {
        $key = 'actionAllowed:'.$key;

        $msec_time = $this->getMsecTime();

        list(,,$count) = $this->redis->pipeline(function ($pipe)use ($key, $msec_time, $period){

            $pipe->zadd($key, $msec_time, $msec_time);

            $pipe->zremrangebyscore($key, 0, $msec_time - $period * 1000);

            $count = $pipe->zcard($key);

            $pipe->expire($key, $period + 1);

            return $count;
        });

        return $count <= $max_count;
    }

    /**
     * 初始化等待锁的进程数量
     *
     * @param Data $data
     */
    private function initQueueLockProcess(Data $data)
    {
        $lua = <<<LUA
            local queueLockProcessName = KEYS[1]
            local expiration           = ARGV[1]
            
            local res = tonumber(redis.call('setnx', queueLockProcessName, 0))
            
            if(res == 1)
            then
                redis.call('expire', queueLockProcessName, expiration)
            end
LUA;

        $this->redis->eval($lua, 1, $data->queue_lock_process_name, $data->expiration);
    }

    /**
     * 初始化lock list
     *
     * @param Data $data
     * @return mixed
     */
    private function initQueueLockList(Data $data)
    {
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

        $this->redis->eval($lua, 2, $data->queue_lock_process_name, $data->queue_lock_list_name, $data->expiration);
        return $data->queue_lock_list_name;
    }

    /**
     * 新增等待进程
     *
     * @param Data $data
     * @throws LockException
     */
    private function addQueueLockProcess(Data $data)
    {
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

        if (!$this->redis->eval($lua, 1, $data->queue_lock_process_name, $data->max_queue_process, $data->expiration)){
            $data->is_del_queue_lock_process = false;
            throw new LockException(self::TOO_MANY_REQUESTS_ERROR_MSG, self::TOO_MANY_REQUESTS_ERROR_CODE);
        }
    }

    /**
     * 新增等待队列list
     *
     * @param Data $data
     */
    private function addQueueLockList(Data $data)
    {
        $this->redis->lpush($data->queue_lock_list_name, true);
        $this->redis->expire($data->queue_lock_list_name, $data->expiration);
    }

    /**
     * 删除当前等待进程
     *
     * @param Data $data
     */
    private function delQueueLockProcess(Data $data)
    {
        if ($data->is_del_queue_lock_process){

            $lua = <<<LUA
            local queueLockProcessName   = KEYS[1]
            local currentQueueProcessNum = tonumber(redis.call('get', queueLockProcessName))
     
            if(currentQueueProcessNum > 0)
            then
                redis.call('decr', queueLockProcessName)
            end
LUA;
            $this->redis->eval($lua, 1, $data->queue_lock_process_name);

            $data->is_del_queue_lock_process = false;
        }
    }


    /**
     * 删除占用锁
     *
     * @param Data $data
     */
    private function delLock(Data $data)
    {
        if ($data->is_del_lock && $data->lock_name){
            $this->redis->eval($this->delLockLua(), 2, $data->lock_name, $data->rand_num);

            $data->is_del_lock = false;
        }
    }

    /**
     * 删除队列锁
     *
     * @param Data $data
     */
    private function delQueueLock(Data $data)
    {
        if ($data->is_del_queue_lock && $data->queue_lock_name){
            $this->redis->eval($this->delLockLua(), 2, $data->queue_lock_name, $data->rand_num);

            $data->is_del_queue_lock = false;
        }
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
     * 防止其他错误
     */
    private function shutdown()
    {
        register_shutdown_function(function (){
            $this->forcedShutdown();
        });
    }

    /**
     * 强制关闭
     *
     */
    private function forcedShutdown()
    {
        if (isset($this->locks)){

            array_walk($this->locks, function ($data){
                $this->delLock($data);
            });

            unset($this->locks);
        }

        if (isset($this->queue_locks)){

            array_walk($this->queue_locks, function ($data){
                $this->delQueueLock($data);
                $this->delQueueLockProcess($data);
            });

            unset($this->queue_locks);
        }
    }

    /**
     * 初始化lock数据对象
     *
     * @param $lock_val
     * @return array
     */
    private function bootLock($lock_val)
    {
        $data = new Data();

        $data->bootLock($lock_val);

        $this->locks[] = $data;

        $current_index = count($this->locks) - 1;

        return [$data, $current_index];
    }

    /**
     * 初始化queueLock数据对象
     *
     * @param $lock_val
     * @param null $max_queue_process
     * @param int $wait_timeout
     * @return array
     */
    private function bootQueueLock($lock_val, $max_queue_process = null, $wait_timeout = 6)
    {
        $data = new Data();

        $data->bootQueueLock($lock_val, $max_queue_process, $wait_timeout);

        $this->queue_locks[] = $data;

        $current_index = count($this->queue_locks) - 1;

        return [$data, $current_index];
    }

    /**
     * 将key放入keys
     *
     * @param $lock_val
     * @throws LockException
     */
    private function pushQueueLockKey($lock_val)
    {
        if (isset($this->queue_lock_keys[$lock_val])){

            throw new LockException(self::EXISTED_ERROR_MSG, self::PARAMS_ERROR_CODE);
        }

        $this->queue_lock_keys[$lock_val] = true;
    }

    /**
     * 将key放入keys
     *
     * @param $lock_val
     * @throws LockException
     */
    private function pushLockKey($lock_val)
    {
        if (isset($this->lock_keys[$lock_val])){

            throw new LockException(self::EXISTED_ERROR_MSG, self::PARAMS_ERROR_CODE);
        }

        $this->lock_keys[$lock_val] = true;
    }

    /**
     * 注册信号
     */
    private function bootSignals()
    {
        if (function_exists('pcntl_async_signals')){
            \pcntl_async_signals(true);

        }

        if (function_exists('pcntl_signal')){
            \pcntl_signal(SIGINT, function(){
                $this->forcedShutdown();
            });
        }

//        pcntl_signal(SIGHUP, function(){
//            $this->forcedShutdown();
//        });
//
//        pcntl_signal(SIGTERM, function(){
//            $this->forcedShutdown();
//        });
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
