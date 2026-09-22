<?php

namespace App\Modules\Workspace\Application\Queries;

final readonly class GetCurrentUserQuery
{
    public function __construct(public string $userId) {}
}
