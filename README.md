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
    return [
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
        ]
    
# lock() 抢占锁
    
    $lock->lock(function($redis){
            echo 'hello world!';
        }, $lock_val);
        
    特点:性能好, 并发高
    缺点:当一个进程获得抢占锁后,其他进程将全被拒绝
# queueLock() 队列锁

    $lock->queueLock(function($redis){
            echo 'hello world!';
        }, $lock_val);
    
    特点:适用于需要排队的系统, 当一个进程获得锁后,其他进程将排队(排队会有队列池,超过队列池的进程会被拒绝,保证系统稳定性)
    缺点:性能低
