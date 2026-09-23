<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Persistence;

use App\Modules\Workspace\Application\Contracts\WorkspaceMemberReadModelInterface;
use App\Modules\Workspace\Application\Dtos\UserDto;
use App\Modules\Workspace\Application\Dtos\WorkspaceMemberDto;
use App\Modules\Workspace\Domain\WorkspaceId;
use Illuminate\Support\Facades\DB;

final class EloquentWorkspaceMemberReadModel implements WorkspaceMemberReadModelInterface
{
    /**
     * @return list<WorkspaceMemberDto>
     */
    public function getMembers(WorkspaceId $workspaceId): array
    {
        $rows = DB::table('workspace_members')
            ->join('users', 'workspace_members.user_id', '=', 'users.id')
            ->where('workspace_members.workspace_id', $workspaceId->value())
            ->orderBy('users.name')
            ->select([
                'users.id as user_id',
                'users.email as user_email',
                'users.name as user_name',
                'workspace_members.role as role',
            ])
            ->get();

        return $rows->map(fn (object $row): WorkspaceMemberDto => new WorkspaceMemberDto(
            user: new UserDto(
                id: (string) $row->user_id,
                email: (string) $row->user_email,
                name: (string) $row->user_name,
            ),
            role: (string) $row->role,
        ))->all();
    }
}
