<?php

namespace App\Modules\SalesAnalytics\Application\Contracts;

use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;

interface SalesAnalyticsReadModelInterface
{
    public function getSalesOverview(string $workspaceId, SalesFilterCriteriaDto $criteria): SalesOverviewDto;

    public function getFilterOptions(string $workspaceId): SalesFilterOptionsDto;

    public function getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto;
}
