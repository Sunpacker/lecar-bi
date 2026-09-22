<?php

namespace App\Modules\Dashboard\Application\Commands;

final readonly class CreateDashboardCommand
{
    public function __construct(
        public string $workspaceId,
        public string $title,
        public ?string $description = null,
    ) {}
}
