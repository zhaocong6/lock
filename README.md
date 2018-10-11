# 环境要求
    
    1.PHP >= 7
    2.composer
    3.redis(必须支持lua)
    4.predis
    
# composer 安装

移步 [composer中文网](https://www.phpcomposer.com/).
# redis 安装

linux移步 [redis中文网](http://www.redis.net.cn/)

window [github redis window](https://github.com/dmajkic/redis/downloads)
# predis 安装
    composer require predis/predis
# lock 安装     
    composer require nabao/lock
# 抢占锁
## lock(callable $callback, string $lock_val, int $expiration = 60)
多进程并发时, 其中某一个进程得到锁后, 其他进程将被拒绝
    
    
    $callback  
                回调函数, 可返回值
    $lock_val
                锁定值
    $expiration
                进程最大执行时间   
       
# 队列锁

## queueLock($closure, $lock_val, $max_queue_process = 100, $expiration = 60) 
多进程并发时, 其中某一个进程得到锁后, 其他进程将等待解锁(配置最大等待进程后, 超过等待数量后进程将被拒绝)

    $callback  
                    回调函数, 可返回值
    $lock_val
                    锁定值
    $max_queue_process        
                    队列最大等待进程        
    $expiration
                    进程最大执行时间   

# 使用
    
    //静态调用
    $lock_val = 'user:pay:1';
    Lock::lock(function($redis){
       echo 'hello world!';
    }, $lock_val);
            
    //实例化调用
    $lock = new Lock();
    $lock_val = 'user:pay:1';
    $lock->lock(function($redis){
        echo 'hello world!';
    }, $lock_val);
    
# config配置
## 目前兼容tp.其它框架请实例化传参

     /*
        |--------------------------------------------------------------------------
        | lock配置文件
        |--------------------------------------------------------------------------
        |
        |drive 锁驱动(默认redis)
        |
        |redis redis驱动配置
        |   host 地址
        |   port 端口
        |
        |params 参数配置
        |   max_queue_process  进程池最大进程
        |   expiration         锁值过期时间
        |
        */
        'lock'=>[
            'drive' =>  'redis',
            'redis' =>  [
                'host'  =>  '127.0.0.1',
                'port'  =>  '6379'
            ],
            'params' => [
                'max_queue_process' => 100
                'expiration'        =>  5
            ]
        ]
