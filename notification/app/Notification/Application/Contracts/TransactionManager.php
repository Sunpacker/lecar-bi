<?php

declare(strict_types=1);

namespace NotificationService\Notification\Application\Contracts;

use Closure;

interface TransactionManager
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function transaction(Closure $operation): mixed;
}
