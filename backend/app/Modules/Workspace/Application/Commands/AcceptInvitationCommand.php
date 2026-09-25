<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

final readonly class AcceptInvitationCommand
{
    public function __construct(
        public string $rawToken,
        public ?string $name = null,
        public ?string $password = null,
    ) {}
}
