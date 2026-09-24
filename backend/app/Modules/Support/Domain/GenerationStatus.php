<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain;

enum GenerationStatus: string
{
    case QUEUED = 'queued';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
