<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;

final readonly class GetSalesFilterOptionsHandler
{
    public function __construct(
        private SalesAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSalesFilterOptionsQuery $query): SalesFilterOptionsDto
    {
        return $this->readModel->getFilterOptions($query->workspaceId);
    }
}
