<?php

declare(strict_types=1);

namespace App\Shared\Application\Ports;

use Closure;

interface TransactionManagerInterface
{
    /**
     * Executes the given callback within a database transaction.
     * If the callback throws, the transaction is rolled back and the exception re-thrown.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed;
}
