<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Queries;

final readonly class GetWorkspaceMembersQuery
{
    public function __construct(
        public string $actorUserId,
        public string $workspaceId,
    ) {}
}
