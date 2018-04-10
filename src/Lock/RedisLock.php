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
    private $max_queue_process = 100;

    //进程名称
    private $queue_lock_process_name;

    //锁值
    private $lock_val;

    //是否删除等待锁进程
    private $is_del_queue_lock_process = true;

    //是否删除锁
    private $is_del_lock = true;

    //随机数
    private $rand_num;

    /**
     * RedisLock constructor.
     * @param $config
     * @param $params
     */
    public function __construct($config = [], $params = [])
    {
        $this->initRedis($config);
        $this->initParams($params);
        $this->randNum();
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
        $this->lock_val = $lock_val;

        if ( $this->redis->set($this->lock_val, $this->rand_num, 'nx', 'ex', $expiration) ) {
            $closure($this->redis);

            $this->delLock();
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
     * @param int $wait_time   默认等待周期0.01s
     *                                          1/0.005=200(1秒最多200个并发) redis cpu 20%(并发同一个lock)
     *                                          1/0.01=100(1秒最多100个并发) redis cpu 15%(并发同一个lock)
     * @throws \Exception
     */

    public function queueLock($closure, $lock_val, $expiration = 60, $max_queue_process = 100, $wait_time = 10000)
    {
        $this->lock_val = $lock_val;

        $this->initQueueLockProcess();

        $this->addQueueLockProcess();

        loop:
        if ( $this->redis->set($this->lock_val, $this->rand_num, 'nx', 'ex', $expiration) ) {
            $closure($this->redis);

            $this->delQueueLockProcess();

            $this->delLock();
        }else{
            usleep($wait_time);
            goto loop;
        }
    }

    /**
     * 设置等待进程名称
     * @return string
     */
    private function setQueueLockProcessName()
    {
        return $this->queue_lock_process_name = 'queueLock:process:'.$this->lock_val;
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
        if ($this->is_del_queue_lock_process){
            $this->redis->decr($this->queue_lock_process_name);

            $this->is_del_queue_lock_process = false;
        }
    }

    /**
     * 删除锁
     */
    private function delLock()
    {
        if ($this->is_del_lock){
            if ($this->rand_num == $this->redis->get($this->lock_val)){
                $this->redis->del($this->lock_val);
            }
            $this->is_del_lock = false;
        }
    }

    /**
     * 生成随机函数
     */
    private function randNum()
    {
        $this->rand_num = uniqid() . mt_rand(1, 1000000);
    }

    /**
     * 初始化redis
     * @param $config
     */
    private function initRedis($config)
    {
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
     * 删除锁
     * 防止程序中止后没解锁
     */
    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        $this->delQueueLockProcess();
        $this->delLock();
    }
}