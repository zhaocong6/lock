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
 * @method $this lock(Callback $closure, $lock_val)
 * @method $this queueLock(Callback $closure, $lock_val, $max_queue_process = 100, $timeout = 60)
 *
 * @author zhaocong <1140253608@qq.com>
 */
interface LockContextInterface{}