<?php

namespace App\Modules\SalesAnalytics\Application\Contracts;

use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;

interface SalesAnalyticsReadModelInterface
{
    public function getSalesOverview(string $workspaceId, SalesFilterCriteriaDto $criteria): SalesOverviewDto;

    public function getFilterOptions(string $workspaceId): SalesFilterOptionsDto;
}
