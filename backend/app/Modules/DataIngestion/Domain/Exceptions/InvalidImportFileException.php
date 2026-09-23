<?php

namespace App\Modules\DataIngestion\Domain\Exceptions;

use RuntimeException;

class InvalidImportFileException extends RuntimeException
{
    public function __construct(string $message = 'Invalid import file.')
    {
        parent::__construct($message);
    }
}
