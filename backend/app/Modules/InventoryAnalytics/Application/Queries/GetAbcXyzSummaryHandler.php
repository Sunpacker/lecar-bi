<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;

final readonly class GetAbcXyzSummaryHandler
{
    public function __construct(
        private InventoryAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetAbcXyzSummaryQuery $query): AbcXyzSummaryDto
    {
        return $this->readModel->getAbcXyzSummary($query->workspaceId, $query->criteria);
    }
}
