<?php

namespace App\Modules\DataIngestion\Domain;

final readonly class RowError
{
    public function __construct(
        public int $rowNumber,
        public ?string $field,
        public ?string $value,
        public string $message
    ) {}
}
