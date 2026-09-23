<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Ports\TransactionManagerInterface;
use Closure;
use Illuminate\Support\Facades\DB;

final class LaravelTransactionManager implements TransactionManagerInterface
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
