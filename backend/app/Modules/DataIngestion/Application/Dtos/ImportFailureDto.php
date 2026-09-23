<?php

namespace App\Modules\DataIngestion\Application\Dtos;

use App\Modules\DataIngestion\Domain\RowError;

final readonly class ImportFailureDto
{
    public function __construct(
        public string $id,
        public int $rowNumber,
        public ?string $field,
        public ?string $value,
        public string $errorMessage,
        public string $createdAt,
    ) {}

    public static function fromRowError(RowError $error, string $id, string $createdAt): self
    {
        return new self(
            id: $id,
            rowNumber: $error->rowNumber,
            field: $error->field,
            value: $error->value,
            errorMessage: $error->message,
            createdAt: $createdAt,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->rowNumber,
            'field' => $this->field,
            'value' => $this->value,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt,
        ];
    }
}
