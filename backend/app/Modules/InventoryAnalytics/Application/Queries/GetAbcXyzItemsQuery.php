<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;

final readonly class GetAbcXyzItemsQuery
{
    public function __construct(
        public string $workspaceId,
        public AbcXyzItemsCriteriaDto $criteria,
    ) {}
}
