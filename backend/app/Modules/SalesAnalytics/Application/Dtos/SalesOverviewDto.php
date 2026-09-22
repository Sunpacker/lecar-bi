<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesOverviewDto
{
    /**
     * @param  list<SalesTrendPointDto>  $trend
     * @param  list<SalesCategoryBreakdownDto>  $categories
     * @param  list<SalesRegionBreakdownDto>  $regions
     */
    public function __construct(
        public SalesSummaryDto $summary,
        public array $trend,
        public array $categories,
        public array $regions,
    ) {}
}
