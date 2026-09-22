<?php

namespace App\Modules\Workspace\Application\Dtos;

final readonly class CurrentWorkspaceDto
{
    public function __construct(
        public UserDto $user,
        public WorkspaceDto $workspace,
    ) {}
}
