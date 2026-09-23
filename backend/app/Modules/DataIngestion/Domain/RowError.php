<?php

namespace App\Modules\DataIngestion\Domain;

use DateTimeImmutable;

final readonly class RowError
{
    public function __construct(
        public int $rowNumber,
        public ?string $field,
        public ?string $value,
        public string $message,
        public ?string $id = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
