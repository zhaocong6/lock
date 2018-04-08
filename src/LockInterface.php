<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-4-8
 * Time: 上午11:50
 */

namespace Lock;

abstract class LockInterface
{

    /**
     * 非等待锁 (适用于限制单个用户行为)
     * 此锁不会等待, 第一个锁用户没有处理完成, 第二个用户将被拒绝
     * @param $closure
     * @param $lock_val
     * @param int $expiration  默认单个任务最大执行时间 60s
     * @throws \Exception
     */
    public function lock($closure, $lock_val, $expiration = 60){}

    /**
     * 等待锁 (此锁堵塞严重 建议配合异步队列)
     * 此锁会等待, 第一个锁用户没有处理完成, 第二个用户将等待
     *
     * 待解决问题:
     *          限制等待用户数量, 防止用户过多造成内存过高
     *
     * @param $closure
     * @param $lock_val
     * @param int $expiration  默认单个任务最大执行时间 60s
     * @param int $wait_time   默认等待周期0.02s
     * @throws \Exception
     */
    public function waitLock($closure, $lock_val, $expiration = 60, $wait_time = 20000){}
}