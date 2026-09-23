<?php

namespace App\Modules\Dashboard\Application\Queries;

final readonly class GetSavedViewByIdQuery
{
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
        public string $viewId,
    ) {}
}
