<?php

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;

final readonly class GetInventorySummaryHandler
{
    public function __construct(
        private InventoryAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetInventorySummaryQuery $query): InventorySummaryDto
    {
        $criteria = new InventorySummaryCriteriaDto(
            warehouseId: $query->warehouseId,
            asOfDate: $query->asOfDate,
        );

        return $this->readModel->getInventorySummary($query->workspaceId, $criteria);
    }
}
