<?php

namespace App\Modules\DataIngestion\Application\Queries;

use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Application\Dtos\PaginatedListDto;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;

final class GetImportBatchesHandler
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
    ) {}

    /**
     * @return PaginatedListDto<ImportBatchDto>
     */
    public function handle(GetImportBatchesQuery $query): PaginatedListDto
    {
        $status = $query->status !== null ? ImportStatus::tryFrom($query->status) : null;
        $total = $this->batchRepository->countByWorkspace($query->workspaceId, $status);
        $batches = $this->batchRepository->listByWorkspace($query->workspaceId, $status, $query->page, $query->perPage);

        $items = array_map(static fn ($b) => ImportBatchDto::fromDomain($b), $batches);
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
