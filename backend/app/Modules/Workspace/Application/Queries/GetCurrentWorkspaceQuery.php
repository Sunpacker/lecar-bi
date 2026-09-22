<?php

namespace App\Modules\Workspace\Application\Queries;

final readonly class GetCurrentWorkspaceQuery
{
    public function __construct(
        public string $userId,
        public ?string $requestedWorkspaceId = null,
    ) {}
}
