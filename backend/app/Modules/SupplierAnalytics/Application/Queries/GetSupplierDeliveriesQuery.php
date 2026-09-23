<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;

final readonly class GetSupplierDeliveriesQuery
{
    public function __construct(
        public string $workspaceId,
        public SupplierDeliveriesCriteriaDto $criteria,
    ) {}
}
