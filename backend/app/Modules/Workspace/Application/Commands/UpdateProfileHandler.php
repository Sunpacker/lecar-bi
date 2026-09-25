<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Application\Dtos\UserDto;
use App\Modules\Workspace\Domain\Exceptions\UserNotFoundException;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;

final readonly class UpdateProfileHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(UpdateProfileCommand $command): UserDto
    {
        $user = $this->userRepository->findById(new UserId($command->userId));
        if ($user === null) {
            throw new UserNotFoundException($command->userId);
        }

        $user->changeName($command->name);
        $this->userRepository->save($user);

        return new UserDto(
            id: $user->id()->value(),
            email: $user->email(),
            name: $user->name(),
        );
    }
}
