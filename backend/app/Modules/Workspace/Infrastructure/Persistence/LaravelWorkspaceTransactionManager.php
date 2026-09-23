<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Persistence;

use App\Modules\Workspace\Application\Contracts\WorkspaceTransactionManagerInterface;
use Closure;
use Illuminate\Support\Facades\DB;

final class LaravelWorkspaceTransactionManager implements WorkspaceTransactionManagerInterface
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
