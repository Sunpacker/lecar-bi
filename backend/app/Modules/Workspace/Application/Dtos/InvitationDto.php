<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Dtos;

final readonly class InvitationDto
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $email,
        public string $role,
        public string $status,
        public string $expiresAt,
        public string $createdAt,
    ) {}
}
