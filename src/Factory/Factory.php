<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-5-8
 * Time: 下午1:06
 */

namespace Lock\Factory;

use Lock\Lock\Redis\Lock as RedisLock;
use Lock\LockException;

class Factory
{
    //实例化lock类
    private $lock;
    //配置文件
    private $config;
    //参数文件
    private $params;
    //驱动
    private $drive;

    //默认配置
    private $default_config = [
        'host'   =>  '127.0.0.1',
        'port'   =>  '6379',
        'drive'  =>  'redis',
    ];

    /**
     * Factory constructor.
     * @param array $config
     * @param array $params
     * @throws LockException
     */
    public function __construct($config = [], $params = [])
    {
        $config = $this->getConfig($config);
        $params = $this->getParams($params);

        $this->getInstance($config, $params);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.

        return call_user_func_array([$this->lock, $name], $arguments);
    }

    /**
     * 实例
     * @param $config
     * @param $params
     * @throws LockException
     */
    private function getInstance($config = [], $params = [])
    {
        if ($this->lock) return $this->lock;

        switch ($this->drive){
            case 'redis':
                $this->lock = new RedisLock($config, $params);
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
    private function getConfig($config = [])
    {
        if ($this->config) return $this->config;

        //判断是否是实例化传值
        if (!empty($config)) {
            $config = array_merge($this->default_config, $config);
            $this->drive = $config['drive'];

            return $this->config = $config[$this->drive];
        }

        //判断是否是tp框架
        if (defined('THINK_VERSION')){
            $this->drive  = C('lock')['drive'];
            $this->config = C('lock')[$this->drive];
        }

        //设置默认参数
        if (empty($this->config)){
            $config      = $this->default_config;
            $this->drive = $config['drive'];
        }

        return $this->config;
    }

    /**
     * @param array $params
     * @return array
     */
    private function getParams($params = [])
    {
        if ($this->params) return $this->params;

        //判断是否是实例化传值
        if (!empty($params)) {
            return $this->params = $params;
        }

        //判断是否是tp框架
        if (defined('THINK_VERSION')){
            $this->params  = C('lock')['params'];
        }

        return $this->params;
    }
}