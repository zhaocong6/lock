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
 * @method $this locks(Callback $closure, $lock_val)
 * @method $this queueLock(Callback $closure,array $lock_val, $max_queue_process = 100, $timeout = 60)
 * @method $this queueLocks(Callback $closure,array $lock_val, $max_queue_process = 100, $timeout = 60)
 * @method $this isActionAllowed($key, $period, $max_count)
 *
 * @author zhaocong <1140253608@qq.com>
 */
interface LockContextInterface{}