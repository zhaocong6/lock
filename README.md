# 环境要求
    
    1.PHP >= 5.6
    2.composer
    3.predis
    
# 安装     
    composer require nabao/lock

# 使用
    
    <?php
    
    use Lock\Lock;
    
    $config = [
        'dirve'=>'redis',
        'redis'=>[
            'host'=>'127.0.0.1',
            'port'=>'6379'
        ]
    ]
    $lock = new Lock($config);
    $lock_val = 'user:pay:1';
    
    $lock->lock(function($redis){
        echo 'hello world!';
    }, $lock_val);
# config配置 (目前兼容tp)
    
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
        */
    return [
        'lock'=>[
                'drive' =>  'redis',
                'redis' =>  [
                    'host'  =>  '127.0.0.1',
                    'port'  =>  '6379'
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
