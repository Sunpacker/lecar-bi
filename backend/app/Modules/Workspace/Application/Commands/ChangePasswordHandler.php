<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Application\Contracts\AuthTokenServiceInterface;
use App\Modules\Workspace\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Workspace\Domain\Exceptions\UserNotFoundException;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuthTokenServiceInterface $tokenService,
    ) {}

    public function handle(ChangePasswordCommand $command): void
    {
        $user = $this->userRepository->findById(new UserId($command->userId));
        if ($user === null) {
            throw new UserNotFoundException($command->userId);
        }

        if (! $user->verifyPassword($command->currentPassword)) {
            throw InvalidCredentialsException::create();
        }

        $newHash = password_hash($command->newPassword, PASSWORD_DEFAULT);
        $user->changePasswordHash($newHash);
        $this->userRepository->save($user);

        // Revoke all existing sessions so user must log in again with new password
        $this->tokenService->revokeAllUserTokens($command->userId);
    }
}
