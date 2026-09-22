<?php

namespace App\Modules\Workspace\Application\Queries;

final readonly class GetAccessibleWorkspacesQuery
{
    public function __construct(public string $userId) {}
}
