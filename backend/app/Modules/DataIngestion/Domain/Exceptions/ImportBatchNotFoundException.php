<?php

namespace App\Modules\DataIngestion\Domain\Exceptions;

use RuntimeException;

class ImportBatchNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Import batch with ID "%s" not found.', $id));
    }
}
