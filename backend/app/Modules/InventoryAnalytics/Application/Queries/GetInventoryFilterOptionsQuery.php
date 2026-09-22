<?php

namespace App\Modules\InventoryAnalytics\Application\Queries;

final readonly class GetInventoryFilterOptionsQuery
{
    public function __construct(
        public string $workspaceId,
    ) {}
}
