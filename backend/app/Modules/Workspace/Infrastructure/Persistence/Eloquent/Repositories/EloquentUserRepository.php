<?php

namespace App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\UserModel;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(UserId $id): ?User
    {
        /** @var UserModel|null $record */
        $record = UserModel::query()->find($id->value());

        if ($record === null) {
            return null;
        }

        return new User(
            id: new UserId((string) $record->id),
            email: (string) $record->email,
            name: (string) $record->name,
        );
    }

    public function save(User $user): void
    {
        UserModel::query()->updateOrCreate(
            ['id' => $user->id()->value()],
            [
                'email' => $user->email(),
                'name' => $user->name(),
            ],
        );
    }
}
