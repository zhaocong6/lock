<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-10-11
 * Time: 上午10:49
 */

namespace Lock\Lock\Redis;

class Data
{

    /**
     * 抢占锁名称
     * @var string
     */
    public $lock_name;

    /**
     * 抢占锁前缀
     * @var string
     */
    private $lock_prefix = 'lock';

    /**
     * 最大允许等待进程
     * @var string
     */
    public $max_queue_process;

    /**
     * timeout
     * @var int
     */
    public $wait_timeout;

    /**
     * key timeout
     * @var int
     */
    public $expiration = 5;

    /**
     * 锁值
     * @var string
     */
    public $lock_val;

    /**
     * 队列锁名称
     * @var string
     */
    public $queue_lock_name;

    /**
     * 队列锁list名称
     *
     * @var string
     */
    public $queue_lock_list_name;

    /**
     * 队列锁前缀
     * @var string
     */
    public $queue_lock_prefix = 'queue:lock';

    /**
     * 队列锁进程数名称
     * @var string
     */
    public $queue_lock_process_name;

    /**
     * 是否删除等待锁进程
     * @var bool
     */
    public $is_del_queue_lock_process = false;

    /**
     * 是否删除等待锁
     * @var bool
     */
    public $is_del_queue_lock = false;

    /**
     * 是否删除锁
     * @var bool
     */
    public $is_del_lock = false;

    /**
     * 随机数
     * @var string
     */
    public $rand_num;

    /**
     * 初始化lock数据
     *
     * @param $lock_val
     */
    public function bootLock($lock_val)
    {
        $this->lock_val = $lock_val;

        $this->is_del_lock                  = true;
        $this->is_del_queue_lock_process    = false;
        $this->is_del_queue_lock            = false;

        $this->randNum();

        $this->setLockName();
    }

    /**
     * 初始化queueLock数据
     *
     * @param $lock_val
     * @param null $max_queue_process
     * @param int $wait_timeout
     */
    public function bootQueueLock($lock_val, $max_queue_process = null, $wait_timeout = 6)
    {
        $this->lock_val             = $lock_val;
        $this->max_queue_process    = $max_queue_process;
        $this->wait_timeout         = $wait_timeout;

        $this->is_del_lock                  = false;
        $this->is_del_queue_lock_process    = true;
        $this->is_del_queue_lock            = true;

        $this->randNum();

        $this->setQueueLockName();

        $this->setQueueLockListName();

        $this->setQueueLockProcessName();
    }

    /**
     * 设置队列锁名称
     *
     * @return string
     */
    private function setQueueLockName()
    {
        return $this->queue_lock_name = $this->queue_lock_prefix. ':' .$this->lock_val;
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
     * 设置等待进程名称
     *
     * @return string
     */
    private function setQueueLockProcessName()
    {
        return $this->queue_lock_process_name = $this->queue_lock_prefix. ':process:' .$this->lock_val;
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
     * 生成随机函数
     *
     * @return string
     */
    private function randNum()
    {
        return $this->rand_num = uniqid() . mt_rand(1, 1000000);
    }
}
