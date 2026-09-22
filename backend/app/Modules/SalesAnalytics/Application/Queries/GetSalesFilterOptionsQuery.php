<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

final readonly class GetSalesFilterOptionsQuery
{
    public function __construct(
        public string $workspaceId,
    ) {}
}
