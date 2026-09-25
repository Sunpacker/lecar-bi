<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Domain\MembershipRole;

final readonly class CreateInvitationCommand
{
    public function __construct(
        public string $workspaceId,
        public string $email,
        public MembershipRole $role,
    ) {}
}
