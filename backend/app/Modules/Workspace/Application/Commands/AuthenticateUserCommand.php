<?php

namespace App\Modules\Workspace\Application\Commands;

final readonly class AuthenticateUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
