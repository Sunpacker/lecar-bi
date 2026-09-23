<?php

namespace App\Modules\DataIngestion\Domain;

enum ImportStatus: string
{
    case PENDING = 'pending';
    case VALIDATING = 'validating';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case COMPLETED_WITH_ERRORS = 'completed_with_errors';
    case FAILED = 'failed';
}
