<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain\Repositories;

use App\Modules\Workspace\Domain\Invitation;
use App\Modules\Workspace\Domain\InvitationId;
use App\Modules\Workspace\Domain\WorkspaceId;

interface InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void;

    public function findById(InvitationId $id): ?Invitation;

    public function findByTokenHash(string $tokenHash): ?Invitation;

    public function findPendingByWorkspaceAndEmail(WorkspaceId $workspaceId, string $email): ?Invitation;

    /**
     * @return list<Invitation>
     */
    public function listPendingByWorkspace(WorkspaceId $workspaceId): array;
}
