<?php

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;

final readonly class GetInventoryFilterOptionsHandler
{
    public function __construct(
        private InventoryAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetInventoryFilterOptionsQuery $query): InventoryFilterOptionsDto
    {
        return $this->readModel->getFilterOptions($query->workspaceId);
    }
}
