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

    //驱动
    private $drive;

    /**
     * Lock constructor.
     * @param array $config
     * @param array $params
     * @throws LockException
     */
    public function __construct($config = [], $params = [])
    {
        $config = $this->getConfig($config);

        $this->instantiation($config, $params);
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
     * @param $config
     * @param $params
     * @throws LockException
     */
    private function instantiation($config, $params)
    {
        switch ($this->drive){
            case 'redis':
                self::$lock = new RedisLock($config, $params);
                break;
            default:
                throw new LockException('该驱动没有对应的类文件!');
        }
    }

    /**
     * 获取配置文件
     * @param $config
     * @return array
     */
    private function getConfig($config)
    {
        //判断是否是实例化传值
        if (!empty($config)) {
            $this->drive = $config['drive'];
            return $this->config = $config[$this->drive];
        }

        //判断是否是tp框架
        if (defined('THINK_VERSION')){
            $this->drive  = C('lock')['drive'];
            $this->config = C('lock')[$this->drive];
        }

        //设置默认参数
        if (empty($config)){
            $config = [
                'host'  =>  '127.0.0.1',
                'port'  =>  '6379'
            ];
            $this->drive = 'redis';
        }

        return $this->config = $config;
    }
}