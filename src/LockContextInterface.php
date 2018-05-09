<?php
/**
 * Created by PhpStorm.
 * User: zc
 * Date: 18-5-9
 * Time: 下午10:44
 */

namespace Lock;

/**
 * 方便ide索引
 *
 * @method $this lock(Closure $closure, String $lock_val, Int $expiration)
 * @method $this queueLock(Closure $closure, String $lock_val, Int $max_queue_process, Int $expiration)
 *
 * @author zhaocong <1140253608@qq.com>
 */
interface LockContextInterface{}