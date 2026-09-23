<?php

namespace App\Modules\DataIngestion\Domain;

use App\Modules\DataIngestion\Domain\Exceptions\CannotRetryImportException;
use DateTimeImmutable;

final class ImportBatch
{
    public function __construct(
        private readonly ImportBatchId $id,
        private readonly string $workspaceId,
        private readonly DatasetType $datasetType,
        private readonly SourceFormat $sourceFormat,
        private readonly string $originalFilename,
        private readonly string $storedFilePath,
        private ImportStatus $status,
        private int $totalRows,
        private int $processedRows,
        private int $successfulRows,
        private int $failedRows,
        private ?string $errorMessage,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt
    ) {
    }

    public function id(): ImportBatchId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function datasetType(): DatasetType
    {
        return $this->datasetType;
    }

    public function sourceFormat(): SourceFormat
    {
        return $this->sourceFormat;
    }

    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    public function storedFilePath(): string
    {
        return $this->storedFilePath;
    }

    public function status(): ImportStatus
    {
        return $this->status;
    }

    public function totalRows(): int
    {
        return $this->totalRows;
    }

    public function processedRows(): int
    {
        return $this->processedRows;
    }

    public function successfulRows(): int
    {
        return $this->successfulRows;
    }

    public function failedRows(): int
    {
        return $this->failedRows;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            ImportStatus::COMPLETED,
            ImportStatus::COMPLETED_WITH_ERRORS,
            ImportStatus::FAILED
        ], true);
    }

    public function startValidation(): void
    {
        $this->status = ImportStatus::VALIDATING;
    }

    public function startProcessing(int $totalRows): void
    {
        $this->status = ImportStatus::PROCESSING;
        $this->totalRows = $totalRows;
    }

    public function recordProgress(int $processed, int $successful, int $failed): void
    {
        $this->processedRows = $processed;
        $this->successfulRows = $successful;
        $this->failedRows = $failed;
    }

    public function markCompleted(): void
    {
        $this->status = ImportStatus::COMPLETED;
        $this->completedAt = new DateTimeImmutable();
    }

    public function markCompletedWithErrors(): void
    {
        $this->status = ImportStatus::COMPLETED_WITH_ERRORS;
        $this->completedAt = new DateTimeImmutable();
    }

    public function markFailed(string $reason): void
    {
        $this->status = ImportStatus::FAILED;
        $this->errorMessage = $reason;
        $this->completedAt = new DateTimeImmutable();
    }

    public function canRetry(): bool
    {
        return in_array($this->status, [
            ImportStatus::FAILED,
            ImportStatus::COMPLETED_WITH_ERRORS
        ], true);
    }

    public function prepareRetry(): void
    {
        if (!$this->canRetry()) {
            throw new CannotRetryImportException();
        }

        $this->status = ImportStatus::PENDING;
        $this->errorMessage = null;
        $this->totalRows = 0;
        $this->processedRows = 0;
        $this->successfulRows = 0;
        $this->failedRows = 0;
        $this->completedAt = null;
    }
}
