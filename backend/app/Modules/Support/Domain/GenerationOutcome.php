<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain;

enum GenerationOutcome: string
{
    case ANSWERED = 'answered';
    case NO_CONTEXT = 'no_context';
}
