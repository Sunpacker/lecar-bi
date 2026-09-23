<?php

namespace App\Modules\DataIngestion\Application\Dtos;

use App\Modules\DataIngestion\Domain\ImportBatch;
use DateTimeInterface;

final readonly class ImportBatchDto
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $datasetType,
        public string $sourceFormat,
        public string $originalFilename,
        public string $storedFilePath,
        public string $status,
        public int $totalRows,
        public int $processedRows,
        public int $successfulRows,
        public int $failedRows,
        public float $progressPercentage,
        public ?string $errorMessage,
        public string $createdAt,
        public ?string $completedAt,
    ) {}

    public static function fromDomain(ImportBatch $batch): self
    {
        $total = $batch->totalRows();
        $processed = $batch->processedRows();
        $progress = $total > 0 ? round(($processed / $total) * 100, 2) : 0.0;
        if ($batch->isCompleted() && in_array($batch->status()->value, ['completed', 'completed_with_errors'], true)) {
            $progress = 100.0;
        }

        return new self(
            id: $batch->id()->toString(),
            workspaceId: $batch->workspaceId(),
            datasetType: $batch->datasetType()->value,
            sourceFormat: $batch->sourceFormat()->value,
            originalFilename: $batch->originalFilename(),
            storedFilePath: $batch->storedFilePath(),
            status: $batch->status()->value,
            totalRows: $batch->totalRows(),
            processedRows: $batch->processedRows(),
            successfulRows: $batch->successfulRows(),
            failedRows: $batch->failedRows(),
            progressPercentage: $progress,
            errorMessage: $batch->errorMessage(),
            createdAt: $batch->createdAt()->format(DateTimeInterface::ATOM),
            completedAt: $batch->completedAt()?->format(DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'dataset_type' => $this->datasetType,
            'source_format' => $this->sourceFormat,
            'original_filename' => $this->originalFilename,
            'stored_file_path' => $this->storedFilePath,
            'status' => $this->status,
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'successful_rows' => $this->successfulRows,
            'failed_rows' => $this->failedRows,
            'progress_percentage' => $this->progressPercentage,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
