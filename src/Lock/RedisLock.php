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

class RedisLock extends LockInterface
{
    //缓存redis
    private $redis;

    //实例化容器
    private static $self;

    //等待计数
    private $loop_num = 0;

    //最大等待次数
    private $loop_num_limit = 20;

    /**
     * 实例化redis 后期版本中增加可配置功能
     * RedisLock constructor.
     * @param $host
     * @param $port
     */
    public function __construct($host = '127.0.0.1', $port = '6379')
    {
        parent::__construct();
    }

    /**
     * 非等待锁 (适用于限制单个用户行为)
     * 此锁不会等待, 第一个锁用户没有处理完成, 第二个用户将被拒绝
     * @param $closure
     * @param $lock_val
     * @param int $expiration  默认单个任务最大执行时间 60s
     * @throws \Exception
     */
    private function lock($closure, $lock_val, $expiration = 60)
    {
        if ( $this->redis->set($lock_val, true, 'nx', 'ex', $expiration) ) {
            $closure($this->redis);

            $this->redis->watch($lock_val);
            $this->redis->multi();
            $this->redis->del($lock_val);
            $this->redis->exec();
        }else{
            throw new LockException('操作频繁, 被服务器拒绝!');
        }
    }

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

    private function waitLock($closure, $lock_val, $expiration = 60, $wait_time = 20000)
    {
        loop:
        if ($this->loop_num > $this->loop_num_limit) { throw new \Exception('等待超时!');};

        if ( $this->redis->set($lock_val, true, 'nx', 'ex', $expiration) ) {
            $closure($this->redis);

            $this->redis->watch($lock_val);
            $this->redis->multi();
            $this->redis->del($lock_val);
            $this->redis->exec();
        }else{
            usleep($wait_time);
            $this->loop_num++;
            goto loop;
        }
    }

    /**
     * 静态调用
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __call() method.
        $self = self::instantiation();
        call_user_func_array([$self, $name], $arguments);
    }

    /**
     * 实例化调用
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        call_user_func_array([$this, $name], $arguments);
    }

    /**
     * 实例化自身
     * @return RedisLock
     */
    private static function instantiation()
    {
        if (!(self::$self instanceof RedisLock)){
            self::$self = new RedisLock();
        }
        return self::$self;
    }
}