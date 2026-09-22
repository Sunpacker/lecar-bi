<?php

namespace App\Modules\Workspace\Domain\Repositories;

use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;

interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;

    public function save(User $user): void;
}
