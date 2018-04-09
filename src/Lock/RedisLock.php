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

class RedisLock extends LockInterface
{
    //缓存redis
    private $redis;

    //队列锁最大进程进程数量
    private $max_queue_process = 50;

    //进程名称
    private $queue_lock_process_name;

    /**
     * RedisLock constructor.
     * @param $config
     * @param $params
     */
    public function __construct($config = [], $params = [])
    {
        $this->initRedis($config);
        $this->initParams($params);
    }

    /**
     * 抢占锁 (适用于限制单个用户行为)
     * 此锁不会等待, 第一个锁用户没有处理完成, 第二个用户将被拒绝
     * @param $closure
     * @param $lock_val
     * @param int $expiration  默认单个任务最大执行时间 60s
     * @throws \Exception
     */
    public function lock($closure, $lock_val, $expiration = 60)
    {
        if ( $this->redis->set($lock_val, true, 'nx', 'ex', $expiration) ) {
            $closure($this->redis);

            $this->delLock($lock_val);
        }else{
            throw new LockException('操作频繁, 被服务器拒绝!');
        }
    }

    /**
     * 队列锁 (此锁堵塞严重 建议配合异步队列)
     * 此锁会等待, 第一个锁用户没有处理完成, 第二个用户将等待
     * @param $closure
     * @param $lock_val
     * @param int $expiration  默认单个任务最大执行时间 60s
     * @param int $max_queue_process   最大进程数
     * @param int $wait_time   默认等待周期0.02s
     * @throws \Exception
     */

    public function queueLock($closure, $lock_val, $expiration = 60, $max_queue_process = 50, $wait_time = 20000)
    {
        $this->initQueueLockProcess($lock_val);

        $this->addQueueLockProcess();

        loop:
        if ( $this->redis->set($lock_val, true, 'nx', 'ex', $expiration) ) {
            $closure($this->redis);

            $this->delQueueLockProcess();

            $this->delLock($lock_val);
        }else{
            usleep($wait_time);
            goto loop;
        }
    }

    /**
     * 设置等待进程名称
     * @param $lock_val
     * @return string
     */
    private function setQueueLockProcessName($lock_val)
    {
        return $this->queue_lock_process_name = 'queueLock:process:'.$lock_val;
    }

    /**
     * 初始化等待锁的进程数量
     * @param $lock_val
     */
    private function initQueueLockProcess($lock_val)
    {
        $queue_lock_process_name = $this->setQueueLockProcessName($lock_val);

        $this->redis->setnx($queue_lock_process_name, 0);
    }

    /**
     * 新增等待进程
     * @throws \Exception
     */
    private function addQueueLockProcess()
    {
        $current_queue_process = $this->redis->get($this->queue_lock_process_name);

        if ($current_queue_process >= $this->max_queue_process){
            throw new LockException('操作频繁, 被服务器拒绝!');
        }else{
            $this->redis->incr($this->queue_lock_process_name);
            $this->redis->expire($this->queue_lock_process_name, 120);
        }
    }

    /**
     * 删除当前等待进程
     */
    private function delQueueLockProcess()
    {
        $this->redis->decr($this->queue_lock_process_name);
    }

    /**
     * 删除锁
     * @param $lock_val
     */
    private function delLock($lock_val)
    {
        $this->redis->watch($lock_val);
        $this->redis->multi();
        $this->redis->del($lock_val);
        $this->redis->exec();
    }

    /**
     * 初始化redis
     * @param $config
     */
    private function initRedis($config)
    {
        $this->redis = new Client([
            'host'       => $config['host'] ? $config['host'] : '127.0.0.1',
            'port'       => $config['port'] ? $config['port'] : '6379',
            'password'   => $config['password'] ? $config['password'] : ''
        ]);
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
}