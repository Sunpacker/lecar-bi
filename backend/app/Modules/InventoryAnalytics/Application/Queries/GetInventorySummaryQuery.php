<?php

namespace App\Modules\InventoryAnalytics\Application\Queries;

final readonly class GetInventorySummaryQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $warehouseId = null,
        public ?string $asOfDate = null,
    ) {}
}
