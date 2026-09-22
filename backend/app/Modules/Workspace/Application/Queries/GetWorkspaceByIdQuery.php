<?php

namespace App\Modules\Workspace\Application\Queries;

final readonly class GetWorkspaceByIdQuery
{
    public function __construct(
        public string $userId,
        public string $workspaceId,
    ) {}
}
