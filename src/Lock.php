<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-4-8
 * Time: 下午1:37
 */

namespace Lock;

use Lock\Lock\RedisLock;

class Lock
{
    private static $lock;

    /**
     * Lock constructor.
     */
    public function __construct()
    {
        self::$lock = new RedisLock();
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
        call_user_func_array([self::$lock, $name], $arguments);
    }

    /**
     * 实例化自身
     * @return RedisLock
     */
    private static function instantiation()
    {
        if (!(self::$lock instanceof RedisLock)){
            self::$lock = new RedisLock();
        }
        return self::$lock;
    }
}