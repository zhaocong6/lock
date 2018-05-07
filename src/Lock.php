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
    //实例化lock类
    private static $lock;

    //配置文件
    private static $config;

    //参数文件
    private static $params;

    //驱动
    private static $drive;

    /**
     * Lock constructor.
     * @param array $config
     * @param array $params
     * @throws LockException
     */
    public function __construct($config = [], $params = [])
    {
        $config = self::getConfig($config);
        $params = self::getParams($params);

        self::manyInstantiation($config, $params);
    }

    /**
     * 静态调用
     * @param $name
     * @param $arguments
     * @throws LockException
     */
    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __call() method.
        self::getConfig();
        self::singleInstantiation(self::$config);
        call_user_func_array([self::$lock, $name], $arguments);
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
     * 单例工厂
     * @param $config
     * @param $params
     * @throws LockException
     */
    private static function singleInstantiation($config = [], $params = [])
    {
        if (self::$lock) return self::$lock;

        switch (self::$drive){
            case 'redis':
                self::$lock = new RedisLock($config, $params);
                break;
            default:
                throw new LockException('该驱动没有对应的类文件!');
        }
    }

    /**
     * 多例工厂
     * @param array $config
     * @param array $params
     * @return RedisLock|null
     * @throws LockException
     */
    private static function manyInstantiation($config = [], $params = [])
    {
        $lock = null;
        switch (self::$drive){
            case 'redis':
                $lock = new RedisLock($config, $params);
                break;
            default:
                throw new LockException('该驱动没有对应的类文件!');
        }

        return $lock;
    }

    /**
     * 获取配置文件
     * @param $config
     * @return array
     */
    private static function getConfig($config = [])
    {
        if (self::$config) return self::$config;

        //判断是否是实例化传值
        if (!empty($config)) {
            self::$drive = $config['drive'];
            return self::$config = $config[self::$drive];
        }

        //判断是否是tp框架
        if (defined('THINK_VERSION')){
            self::$drive  = C('lock')['drive'];
            self::$config = C('lock')[self::$drive];
        }

        //设置默认参数
        if (empty(self::$config)){
            self::$config = [
                'host'  =>  '127.0.0.1',
                'port'  =>  '6379'
            ];
            self::$drive = 'redis';
        }

        return self::$config;
    }

    /**
     * @param array $params
     * @return array
     */
    private function getParams($params = [])
    {
        if (self::$params) return self::$params;

        //判断是否是实例化传值
        if (!empty($params)) {
            return self::$params = $params;
        }

        //判断是否是tp框架
        if (defined('THINK_VERSION')){
            self::$params  = C('lock')['params'];
        }

        return self::$params;
    }

    /**
     * 防止实例从外部被克隆
     *
     * @return void
     */
    private function __clone(){}

    /**
     * 防止实例从外部反序列化
     *
     * @return void
     */
    private function __wakeup(){}
}
