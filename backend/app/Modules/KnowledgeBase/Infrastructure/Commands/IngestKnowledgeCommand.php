<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Commands;

use App\Modules\KnowledgeBase\Infrastructure\Ingestion\KnowledgeIngestionService;
use App\Modules\KnowledgeBase\Infrastructure\Jobs\IngestKnowledgeJob;
use Illuminate\Console\Command;
use Throwable;

final class IngestKnowledgeCommand extends Command
{
    protected $signature = 'support:knowledge:ingest {--manifest=} {--sync}';

    protected $description = 'Build and atomically publish the allowlisted support knowledge corpus';

    public function handle(KnowledgeIngestionService $service): int
    {
        $root = dirname(base_path());
        $manifest = (string) ($this->option('manifest') ?: $root.'/docs/support/manifest.json');
        if (! $this->option('sync')) {
            IngestKnowledgeJob::dispatch($manifest, $root)->onQueue((string) config('support.indexing_queue'));
            $this->info('Knowledge ingestion queued.');

            return self::SUCCESS;
        }

        try {
            $result = $service->ingest($manifest, $root);
        } catch (Throwable) {
            $this->error('Knowledge ingestion failed. Inspect safe operational metrics for the failure stage.');

            return self::FAILURE;
        }
        $this->info("Build {$result['build_id']} is {$result['status']} with {$result['chunks']} chunks.");

        return self::SUCCESS;
    }
}
