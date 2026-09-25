<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Queries;

final readonly class ListInvitationsQuery
{
    public function __construct(
        public string $workspaceId,
    ) {}
}
