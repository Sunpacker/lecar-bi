<?php

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;

final readonly class GetInventoryItemsHandler
{
    public function __construct(
        private InventoryAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetInventoryItemsQuery $query): InventoryItemsPaginatedDto
    {
        $criteria = new InventoryItemsCriteriaDto(
            warehouseId: $query->warehouseId,
            stockHealth: $query->stockHealth,
            search: $query->search,
            page: max(1, $query->page),
            perPage: min(100, max(1, $query->perPage)),
            sortBy: $query->sortBy,
            sortDirection: strtolower($query->sortDirection) === 'desc' ? 'desc' : 'asc',
        );

        return $this->readModel->getInventoryItems($query->workspaceId, $criteria);
    }
}
