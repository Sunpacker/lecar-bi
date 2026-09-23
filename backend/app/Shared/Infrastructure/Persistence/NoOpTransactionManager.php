<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Ports\TransactionManagerInterface;
use Closure;

/**
 * No-op transaction manager for unit tests.
 * Executes the callback directly without starting a real database transaction.
 */
final class NoOpTransactionManager implements TransactionManagerInterface
{
    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }
}
