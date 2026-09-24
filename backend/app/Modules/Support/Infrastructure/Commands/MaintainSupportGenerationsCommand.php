<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Commands;

use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use Illuminate\Console\Command;

final class MaintainSupportGenerationsCommand extends Command
{
    protected $signature = 'support:maintain';

    protected $description = 'Fence expired generation leases and purge revoked conversations after the grace period';

    public function handle(SupportRepositoryInterface $repository): int
    {
        $failed = $repository->failExpiredLeases();
        $deleted = $repository->cleanupExpired();
        $this->info("Failed {$failed} expired generations; deleted {$deleted} conversations.");

        return self::SUCCESS;
    }
}
