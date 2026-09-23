<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Dtos;

final readonly class WorkspaceMemberDto
{
    public function __construct(
        public UserDto $user,
        public string $role,
    ) {}
}
