<?php

namespace App\Modules\InventoryAnalytics\Application\Queries;

final readonly class GetInventoryItemsQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $warehouseId = null,
        public ?string $stockHealth = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'quantity_available',
        public string $sortDirection = 'asc',
    ) {}
}
