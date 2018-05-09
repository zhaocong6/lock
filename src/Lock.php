<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-4-8
 * Time: 下午1:37
 */

namespace Lock;

use Lock\Factory\Factory;

class Lock implements LockContextInterface
{
    private static $single;
    private $many;

    /**
     * Lock constructor.
     * @param array $config
     * @param array $params
     * @throws LockException
     */
    public function __construct($config = [], $params = [])
    {
        $this->manyInstance($config, $params);
    }

    /**
     * 单例
     * @param $name
     * @param $arguments
     * @throws LockException
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        $single = self::singleInstance();

        return call_user_func_array([$single, $name], $arguments);
    }

    /**
     * 实例化调用
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->many, $name], $arguments);
    }

    /**
     * 单例工厂
     * @return Factory
     * @throws LockException
     */
    private static function singleInstance()
    {
        if (empty(self::$single)){
            self::$single = new Factory();
        }

        return self::$single;
    }

    /**
     * 多例工厂
     * @param $config
     * @param $params
     * @return Factory
     * @throws LockException
     */
    private function manyInstance($config, $params)
    {
        if (empty($this->many)){
            $this->many = new Factory($config, $params);
        }

        return $this->many;
    }

    private function __clone(){}
    private function __wakeup(){}
}
