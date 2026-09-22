<?php

namespace App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceMemberModel;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceModel;

final class EloquentWorkspaceRepository implements WorkspaceRepositoryInterface
{
    public function findById(WorkspaceId $id): ?Workspace
    {
        /** @var WorkspaceModel|null $record */
        $record = WorkspaceModel::query()->with('members')->find($id->value());

        if ($record === null) {
            return null;
        }

        return $this->toDomain($record);
    }

    /** @return list<Workspace> */
    public function findByUserId(UserId $userId): array
    {
        $records = WorkspaceModel::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $userId->value()))
            ->with('members')
            ->get();

        return $records->map(fn (WorkspaceModel $model) => $this->toDomain($model))->values()->all();
    }

    public function save(Workspace $workspace): void
    {
        /** @var WorkspaceModel $model */
        $model = WorkspaceModel::query()->updateOrCreate(
            ['id' => $workspace->id()->value()],
            [
                'name' => $workspace->name(),
                'slug' => $workspace->slug(),
            ],
        );

        foreach ($workspace->memberships() as $membership) {
            WorkspaceMemberModel::query()->updateOrCreate(
                [
                    'workspace_id' => $membership->workspaceId()->value(),
                    'user_id' => $membership->userId()->value(),
                ],
                [
                    'role' => $membership->role()->value,
                ],
            );
        }
    }

    private function toDomain(WorkspaceModel $model): Workspace
    {
        $workspace = new Workspace(
            id: new WorkspaceId((string) $model->id),
            name: (string) $model->name,
            slug: (string) $model->slug,
        );

        foreach ($model->members as $member) {
            $role = MembershipRole::tryFrom((string) $member->role) ?? MembershipRole::MEMBER;
            $workspace->addMember(new UserId((string) $member->user_id), $role);
        }

        return $workspace;
    }
}
