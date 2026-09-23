<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformancePaginatedDto;

final readonly class GetSupplierPerformanceHandler
{
    public function __construct(
        private SupplierAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSupplierPerformanceQuery $query): SupplierPerformancePaginatedDto
    {
        return $this->readModel->getSupplierPerformance($query->workspaceId, $query->criteria);
    }
}
