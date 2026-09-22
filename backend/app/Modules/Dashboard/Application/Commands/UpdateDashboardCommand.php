<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\WidgetDto;

final readonly class UpdateDashboardCommand
{
    /**
     * @param  list<WidgetDto>  $widgets
     */
    public function __construct(
        public string $workspaceId,
        public string $dashboardId,
        public string $title,
        public ?string $description = null,
        public array $widgets = [],
    ) {}
}
