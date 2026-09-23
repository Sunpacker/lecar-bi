<?php

namespace App\Modules\DataIngestion\Domain\Exceptions;

use RuntimeException;

class CannotRetryImportException extends RuntimeException
{
    public function __construct(string $message = 'Cannot retry import batch in its current state.')
    {
        parent::__construct($message);
    }
}
