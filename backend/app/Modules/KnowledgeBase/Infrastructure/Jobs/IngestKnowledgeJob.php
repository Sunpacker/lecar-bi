<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Jobs;

use App\Modules\KnowledgeBase\Infrastructure\Ingestion\KnowledgeIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class IngestKnowledgeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly string $manifestPath,
        public readonly string $repositoryRoot,
    ) {}

    public function uniqueId(): string
    {
        return hash_file('sha256', $this->manifestPath) ?: $this->manifestPath;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(KnowledgeIngestionService $service): void
    {
        $service->ingest($this->manifestPath, $this->repositoryRoot);
    }
}
