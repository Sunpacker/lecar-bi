<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Dtos;

final readonly class InvitationPublicDto
{
    public function __construct(
        public string $email,
        public string $workspaceName,
        public string $role,
        public bool $isExpired,
        public bool $isExistingUser,
    ) {}
}
