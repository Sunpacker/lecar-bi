<?php

declare(strict_types=1);

namespace NotificationService\Console\Commands;

use Illuminate\Console\Command;
use NotificationService\Integration\Infrastructure\Services\WorkerHeartbeatService;

final class WorkerHealthCommand extends Command
{
    protected $signature = 'notifications:worker-health 
                            {--threshold=30 : Maximum allowed seconds since last heartbeat}
                            {--json : Output health signals as JSON}';

    protected $description = 'Check notification worker heartbeat health signals.';

    public function handle(WorkerHeartbeatService $service): int
    {
        $threshold = (int) $this->option('threshold');
        $status = $service->getWorkerStatus($threshold);

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Worker Status: {$status['status']}");
            $this->line('  Worker ID: '.($status['worker']['worker_id'] ?? 'unknown'));
            $this->line('  Heartbeat Age: '.($status['worker']['heartbeat_age_seconds'] ?? 'N/A').' s');
            $this->line('  Processed Messages: '.$status['worker']['processed_count']);
            $this->line('  Failed Messages: '.$status['worker']['failed_count']);
            $this->line('  Pending in Stream: '.$status['stream']['pending_messages']);
            $this->line('  Dead-Letter Count: '.$status['stream']['dead_letter_count']);
        }

        return $status['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
