<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public string $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {}
}
