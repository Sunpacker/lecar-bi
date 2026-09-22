<?php

namespace App\Modules\Workspace\Application\Queries;

use App\Modules\Workspace\Application\Dtos\UserDto;
use App\Modules\Workspace\Domain\Exceptions\UserNotFoundException;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;

final readonly class GetCurrentUserHandler
{
    public function __construct(private UserRepositoryInterface $userRepository) {}

    public function handle(GetCurrentUserQuery $query): UserDto
    {
        $user = $this->userRepository->findById(new UserId($query->userId));

        if ($user === null) {
            throw UserNotFoundException::forId($query->userId);
        }

        return new UserDto(
            id: $user->id()->value(),
            email: $user->email(),
            name: $user->name(),
        );
    }
}
