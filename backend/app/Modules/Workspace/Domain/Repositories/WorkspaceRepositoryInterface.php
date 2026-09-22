<?php

namespace App\Modules\Workspace\Domain\Repositories;

use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;

interface WorkspaceRepositoryInterface
{
    public function findById(WorkspaceId $id): ?Workspace;

    /** @return list<Workspace> */
    public function findByUserId(UserId $userId): array;

    public function save(Workspace $workspace): void;
}
