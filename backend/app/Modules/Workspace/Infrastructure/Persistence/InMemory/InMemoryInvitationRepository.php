<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Persistence\InMemory;

use App\Modules\Workspace\Domain\Invitation;
use App\Modules\Workspace\Domain\InvitationId;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;

final class InMemoryInvitationRepository implements InvitationRepositoryInterface
{
    /** @var array<string, Invitation> id => Invitation */
    private array $invitations = [];

    public function save(Invitation $invitation): void
    {
        $this->invitations[$invitation->id()->value()] = $invitation;
    }

    public function findById(InvitationId $id): ?Invitation
    {
        return $this->invitations[$id->value()] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?Invitation
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation->tokenHash() === $tokenHash) {
                return $invitation;
            }
        }

        return null;
    }

    public function findPendingByWorkspaceAndEmail(WorkspaceId $workspaceId, string $email): ?Invitation
    {
        $targetEmail = strtolower(trim($email));
        foreach ($this->invitations as $invitation) {
            if ($invitation->workspaceId()->value() === $workspaceId->value()
                && strtolower(trim($invitation->email())) === $targetEmail
                && $invitation->isPending()) {
                return $invitation;
            }
        }

        return null;
    }

    public function listPendingByWorkspace(WorkspaceId $workspaceId): array
    {
        $result = [];
        foreach ($this->invitations as $invitation) {
            if ($invitation->workspaceId()->value() === $workspaceId->value() && $invitation->isPending()) {
                $result[] = $invitation;
            }
        }

        return $result;
    }
}
