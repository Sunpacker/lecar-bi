<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewDto;

final readonly class GetSupplierOverviewHandler
{
    public function __construct(
        private SupplierAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSupplierOverviewQuery $query): SupplierOverviewDto
    {
        return $this->readModel->getSupplierOverview($query->workspaceId, $query->criteria);
    }
}
