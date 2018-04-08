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
    private $config;

    /**
     * Lock constructor.
     * @param string $host
     * @param string $port
     */
    public function __construct($host = '127.0.0.1', $port = '6379')
    {
        $this->getConfig();

        $this->instantiation();
    }

    /**
     * 静态调用
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __call() method.
        if (!(self::$lock instanceof RedisLock)){
            self::$lock = new RedisLock();
        }
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
     * 工厂实例化
     */
    private function instantiation()
    {
        switch ($this->config['drive']){
            case 'redis':
                self::$lock = new RedisLock($this->config['host'], $this->config['port']);
                break;
        }
    }

    /**
     * 获取配置文件
     * @return null
     */
    private function getConfig()
    {
        $config = null;
        //判断是否是tp框架
        if (defined('THINK_VERSION')){
            $config = C('lock');
        }

        if (empty($config)){
            $config = [
                'drive' =>  'redis',
                'host'  =>  '127.0.0.1',
                'port'  =>  '6379'
            ];
        }
        return $this->config = $config;
    }
}