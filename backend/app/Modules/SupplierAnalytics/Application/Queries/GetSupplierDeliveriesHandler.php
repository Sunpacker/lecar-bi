<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesPaginatedDto;

final readonly class GetSupplierDeliveriesHandler
{
    public function __construct(
        private SupplierAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSupplierDeliveriesQuery $query): SupplierDeliveriesPaginatedDto
    {
        return $this->readModel->getSupplierDeliveries($query->workspaceId, $query->criteria);
    }
}
