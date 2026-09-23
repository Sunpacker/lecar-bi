<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Contracts;

use App\Modules\Workspace\Application\Dtos\WorkspaceMemberDto;
use App\Modules\Workspace\Domain\WorkspaceId;

interface WorkspaceMemberReadModelInterface
{
    /**
     * @return list<WorkspaceMemberDto>
     */
    public function getMembers(WorkspaceId $workspaceId): array;
}
