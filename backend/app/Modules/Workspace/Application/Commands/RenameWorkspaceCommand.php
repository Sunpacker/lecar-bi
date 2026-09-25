<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

final readonly class RenameWorkspaceCommand
{
    public function __construct(
        public string $workspaceId,
        public string $name,
    ) {}
}
