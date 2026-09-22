<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;

final readonly class GetAbcXyzSummaryQuery
{
    public function __construct(
        public string $workspaceId,
        public AbcXyzSummaryCriteriaDto $criteria,
    ) {}
}
