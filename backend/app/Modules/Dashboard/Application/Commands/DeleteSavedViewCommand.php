<?php

namespace App\Modules\Dashboard\Application\Commands;

final readonly class DeleteSavedViewCommand
{
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
        public string $viewId,
    ) {}
}
