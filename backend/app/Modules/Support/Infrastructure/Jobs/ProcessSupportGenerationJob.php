<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Jobs;

use App\Modules\Support\Application\GenerationProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessSupportGenerationJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 95;

    public function __construct(public readonly string $generationId) {}

    public function uniqueId(): string
    {
        return $this->generationId;
    }

    public function handle(GenerationProcessor $processor): void
    {
        $processor->process($this->generationId);
    }
}
