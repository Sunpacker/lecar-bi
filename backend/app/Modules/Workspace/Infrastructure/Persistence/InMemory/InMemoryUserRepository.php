<?php

namespace App\Modules\Workspace\Infrastructure\Persistence\InMemory;

use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value()] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        $normalized = strtolower(trim($email));

        foreach ($this->users as $user) {
            if (strtolower($user->email()) === $normalized) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $this->users[$user->id()->value()] = $user;
    }
}
