<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Contracts;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;

interface InventoryAnalyticsReadModelInterface
{
    public function getInventorySummary(string $workspaceId, InventorySummaryCriteriaDto $criteria): InventorySummaryDto;

    public function getInventoryItems(string $workspaceId, InventoryItemsCriteriaDto $criteria): InventoryItemsPaginatedDto;

    public function getFilterOptions(string $workspaceId): InventoryFilterOptionsDto;

    public function getAbcXyzSummary(string $workspaceId, AbcXyzSummaryCriteriaDto $criteria): AbcXyzSummaryDto;

    public function getAbcXyzItems(string $workspaceId, AbcXyzItemsCriteriaDto $criteria): AbcXyzProductItemsPaginatedDto;
}
