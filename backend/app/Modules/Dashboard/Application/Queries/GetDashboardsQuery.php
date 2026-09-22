<?php

namespace App\Modules\Dashboard\Application\Queries;

final readonly class GetDashboardsQuery
{
    public function __construct(
        public string $workspaceId,
    ) {}
}
