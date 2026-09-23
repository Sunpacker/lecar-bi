<?php

namespace App\Modules\DataIngestion\Application\Queries;

use App\Modules\DataIngestion\Application\Dtos\ImportFailureDto;
use App\Modules\DataIngestion\Application\Dtos\PaginatedListDto;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;

final class GetImportFailuresHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
        private readonly ImportFailureRepositoryInterface $failureRepository,
    ) {}

    /**
     * @return PaginatedListDto<ImportFailureDto>
     */
    public function handle(GetImportFailuresQuery $query): PaginatedListDto
    {
        $batchId = ImportBatchId::fromString($query->batchId);
        $batch = $this->batchRepository->findById($batchId, $query->workspaceId);

        if ($batch === null) {
            throw new ImportBatchNotFoundException("Import batch '{$query->batchId}' not found in current workspace.");
        }

        $total = $this->failureRepository->countByBatchId($batchId, $query->workspaceId);
        $errors = $this->failureRepository->listByBatchId($batchId, $query->workspaceId, $query->page, $query->perPage);

        $items = array_map(static function ($err) use ($batchId) {
            $id = $err->id ?? ($batchId->toString().'-'.$err->rowNumber);
            $createdAt = $err->createdAt !== null
                ? $err->createdAt->format(DateTimeInterface::ATOM)
                : (new DateTimeImmutable)->format(DateTimeInterface::ATOM);

            return ImportFailureDto::fromRowError($err, $id, $createdAt);
        }, $errors);

        $totalPages = $query->perPage > 0 ? (int) ceil($total / $query->perPage) : 1;

        return new PaginatedListDto(
            items: array_values($items),
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            totalPages: max(1, $totalPages),
        );
    }
}
