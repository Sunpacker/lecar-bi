<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Contracts;

use Closure;

interface WorkspaceTransactionManagerInterface
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed;
}
