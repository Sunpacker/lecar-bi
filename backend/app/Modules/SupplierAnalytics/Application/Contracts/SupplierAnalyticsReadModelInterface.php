<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Contracts;

use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesPaginatedDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierFilterOptionsDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformancePaginatedDto;

interface SupplierAnalyticsReadModelInterface
{
    public function getSupplierOverview(string $workspaceId, SupplierOverviewCriteriaDto $criteria): SupplierOverviewDto;

    public function getSupplierPerformance(string $workspaceId, SupplierPerformanceCriteriaDto $criteria): SupplierPerformancePaginatedDto;

    public function getSupplierDeliveries(string $workspaceId, SupplierDeliveriesCriteriaDto $criteria): SupplierDeliveriesPaginatedDto;

    public function getFilterOptions(string $workspaceId): SupplierFilterOptionsDto;
}
