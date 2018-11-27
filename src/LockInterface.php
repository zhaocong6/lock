<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-4-8
 * Time: 上午11:50
 */

namespace Lock;

interface LockInterface
{

    /**
     * 抢占锁 (适用于限制单个用户行为)
     * 此锁不会等待, 第一个锁用户没有处理完成, 第二个用户将被拒绝
     * @param $closure
     * @param $lock_val
     * @throws \Exception
     */
    public function lock($closure, $lock_val);

    /**
     * @param $closure
     * @param $lock_vals
     * @return mixed
     */
    public function locks($closure, $lock_vals);

    /**
     * 队列锁
     * 此锁会等待, 第一个锁用户没有处理完成, 第二个用户将等待
     * @param $closure
     * @param $lock_val
     * @param int $max_queue_process   最大等待进程数
     * @param int $timeout  默认单个任务最大执行时间 60s
     * @throws \Exception
     */
    public function queueLock($closure, $lock_val, $max_queue_process = 100, $timeout = 60);

    /**
     * @param $closure
     * @param $lock_vals
     * @param int $max_queue_process
     * @param int $timeout
     * @return mixed
     */
    public function queueLocks($closure, $lock_vals, $max_queue_process = 100, $timeout = 60);
}