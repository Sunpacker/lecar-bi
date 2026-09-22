<?php

namespace App\Modules\Workspace\Application\Dtos;

final readonly class UserDto
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
    ) {}
}
