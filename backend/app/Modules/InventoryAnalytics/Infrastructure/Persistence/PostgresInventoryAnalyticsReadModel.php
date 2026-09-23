<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\AbcXyzAnalyticsQuery;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\InventoryFilterOptionsQuery;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\InventoryItemsQuery;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\InventorySummaryQuery;

final class PostgresInventoryAnalyticsReadModel implements InventoryAnalyticsReadModelInterface
{
    public function __construct(
        private readonly InventorySummaryQuery $summaryQuery = new InventorySummaryQuery,
        private readonly InventoryItemsQuery $itemsQuery = new InventoryItemsQuery,
        private readonly InventoryFilterOptionsQuery $filterOptionsQuery = new InventoryFilterOptionsQuery,
        private readonly AbcXyzAnalyticsQuery $abcXyzQuery = new AbcXyzAnalyticsQuery,
    ) {}

    public function getInventorySummary(string $workspaceId, InventorySummaryCriteriaDto $criteria): InventorySummaryDto
    {
        return $this->summaryQuery->execute($workspaceId, $criteria);
    }

    public function getInventoryItems(string $workspaceId, InventoryItemsCriteriaDto $criteria): InventoryItemsPaginatedDto
    {
        return $this->itemsQuery->execute($workspaceId, $criteria);
    }

    public function getFilterOptions(string $workspaceId): InventoryFilterOptionsDto
    {
        return $this->filterOptionsQuery->execute($workspaceId);
    }

    public function getAbcXyzSummary(string $workspaceId, AbcXyzSummaryCriteriaDto $criteria): AbcXyzSummaryDto
    {
        return $this->abcXyzQuery->getSummary($workspaceId, $criteria);
    }

    public function getAbcXyzItems(string $workspaceId, AbcXyzItemsCriteriaDto $criteria): AbcXyzProductItemsPaginatedDto
    {
        return $this->abcXyzQuery->getItems($workspaceId, $criteria);
    }
}
