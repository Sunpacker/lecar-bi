<?php

namespace App\Modules\Workspace\Application\Dtos;

final readonly class WorkspaceDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $role,
    ) {}
}
