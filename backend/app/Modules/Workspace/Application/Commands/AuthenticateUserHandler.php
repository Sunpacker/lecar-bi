<?php

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\User;

final readonly class AuthenticateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(AuthenticateUserCommand $command): User
    {
        $user = $this->userRepository->findByEmail($command->email);

        if ($user === null || ! $user->verifyPassword($command->password)) {
            throw InvalidCredentialsException::create();
        }

        return $user;
    }
}
