<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Queries;

final readonly class GetInvitationDetailsQuery
{
    public function __construct(
        public string $rawToken,
    ) {}
}
