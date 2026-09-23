<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;

final readonly class GetSupplierOverviewQuery
{
    public function __construct(
        public string $workspaceId,
        public SupplierOverviewCriteriaDto $criteria,
    ) {}
}
