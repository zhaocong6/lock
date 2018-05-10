# 环境要求
    
    1.PHP >= 5.6
    2.composer
    3.redis
    4.predis
    
# composer 安装

移步 [composer中文网](https://www.phpcomposer.com/).
# redis 安装
redis不支持window平台, window平台下的redis服务器由微软团队维护,版本一般比较旧.
一些redis新数据结构和功能会有限制,建议window用户安装linux虚拟机

linux移步 [redis中文网](http://www.redis.net.cn/)

window [github redis window](https://github.com/dmajkic/redis/downloads)
# predis 安装
    composer require predis/predis
# lock 安装     
    composer require nabao/lock

# 使用
    
    //静态调用
    //不需要实例化,使用方便.配置
    <?php
        
    use Lock\Lock;
        
    $lock_val = 'user:pay:1';
        
    Lock::lock(function($redis){
       echo 'hello world!';
    }, $lock_val);
            
    //实例化调用
    <?php
    
    use Lock\Lock;
   
    $lock = new Lock();
    $lock_val = 'user:pay:1';
    
    $lock->lock(function($redis){
        echo 'hello world!';
    }, $lock_val);
    
# config配置
##目前兼容tp.其它框架请实例化传参

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
            ]
        ]
    
# lock() 抢占锁
    
    lock(callable $callback, string $lock_val, int $expiration = 60);
    
    $callback  
                回调函数, 可返回值
    $lock_val
                锁定值
    $expiration
                进程最大执行时间   
       
# queueLock() 队列锁

    queueLock($closure, $lock_val, $max_queue_process = 100, $expiration = 60)
    $callback  
                    回调函数, 可返回值
    $lock_val
                    锁定值
    $max_queue_process        
                    队列最大等待进程        
    $expiration
                    进程最大执行时间   
