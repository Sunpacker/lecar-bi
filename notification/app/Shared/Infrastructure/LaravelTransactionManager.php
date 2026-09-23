<?php

declare(strict_types=1);

namespace NotificationService\Shared\Infrastructure;

use Closure;
use Illuminate\Support\Facades\DB;
use NotificationService\Notification\Application\Contracts\TransactionManager;

final class LaravelTransactionManager implements TransactionManager
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function transaction(Closure $operation): mixed
    {
        return DB::transaction($operation);
    }
}
