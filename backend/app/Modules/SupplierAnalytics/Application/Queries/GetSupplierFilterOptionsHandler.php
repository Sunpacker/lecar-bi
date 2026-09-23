<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierFilterOptionsDto;

final readonly class GetSupplierFilterOptionsHandler
{
    public function __construct(
        private SupplierAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSupplierFilterOptionsQuery $query): SupplierFilterOptionsDto
    {
        return $this->readModel->getFilterOptions($query->workspaceId);
    }
}
