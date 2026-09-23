<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

final readonly class ChangeWorkspaceMemberRoleCommand
{
    public function __construct(
        public string $actorUserId,
        public string $workspaceId,
        public string $targetUserId,
        public string $role,
    ) {}
}
