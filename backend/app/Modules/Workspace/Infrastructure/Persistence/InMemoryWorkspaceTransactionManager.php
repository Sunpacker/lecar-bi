<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Persistence;

use App\Modules\Workspace\Application\Contracts\WorkspaceTransactionManagerInterface;
use Closure;

final class InMemoryWorkspaceTransactionManager implements WorkspaceTransactionManagerInterface
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        return $callback();
    }
}
