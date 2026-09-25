<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Commands;

use App\Shared\Infrastructure\Health\OutboxHealthService;
use Illuminate\Console\Command;

final class OutboxHealthCommand extends Command
{
    protected $signature = 'outbox:health {--json : Output health signals as JSON}';

    protected $description = 'Check outbox backlog and publisher heartbeat health signals.';

    public function handle(OutboxHealthService $service): int
    {
        $health = $service->checkHealth();

        if ($this->option('json')) {
            $this->line((string) json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Outbox Status: {$health['status']}");
            $this->line("  Pending Messages: {$health['outbox']['pending_count']}");
            $this->line('  Oldest Pending Age: '.($health['outbox']['oldest_pending_age_seconds'] ?? 'N/A').' s');
            $this->line("  Failed Messages: {$health['outbox']['failed_count']}");
            $this->line('  Publisher Last Run: '.($health['publisher']['last_run_at'] ?? 'never'));
            $this->line('  Publisher Age: '.($health['publisher']['last_run_age_seconds'] ?? 'N/A').' s');
        }

        return $health['status'] === 'degraded' ? self::FAILURE : self::SUCCESS;
    }
}
