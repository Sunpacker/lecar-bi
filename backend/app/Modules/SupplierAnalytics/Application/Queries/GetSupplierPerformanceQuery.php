<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;

final readonly class GetSupplierPerformanceQuery
{
    public function __construct(
        public string $workspaceId,
        public SupplierPerformanceCriteriaDto $criteria,
    ) {}
}
