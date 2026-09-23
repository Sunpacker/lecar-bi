<?php

namespace App\Modules\DataIngestion\Infrastructure\Validators;

use App\Modules\DataIngestion\Domain\RowError;

interface RowValidatorInterface
{
    /**
     * Validate a single row.
     *
     * @param  array<string, string>  $row
     * @return list<RowError> Empty array means valid.
     */
    public function validate(array $row, int $rowNumber): array;
}
