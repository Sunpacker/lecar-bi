<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Workspace\Domain\Invitation;
use App\Modules\Workspace\Domain\InvitationId;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\InvitationModel;
use DateTimeImmutable;

final class EloquentInvitationRepository implements InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void
    {
        InvitationModel::updateOrCreate(
            ['id' => $invitation->id()->value()],
            [
                'workspace_id' => $invitation->workspaceId()->value(),
                'email' => $invitation->email(),
                'role' => $invitation->role()->value,
                'token_hash' => $invitation->tokenHash(),
                'status' => $invitation->status(),
                'expires_at' => $invitation->expiresAt(),
                'accepted_at' => $invitation->acceptedAt(),
            ]
        );
    }

    public function findById(InvitationId $id): ?Invitation
    {
        $model = InvitationModel::find($id->value());
        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByTokenHash(string $tokenHash): ?Invitation
    {
        $model = InvitationModel::where('token_hash', $tokenHash)->first();
        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findPendingByWorkspaceAndEmail(WorkspaceId $workspaceId, string $email): ?Invitation
    {
        $model = InvitationModel::where('workspace_id', $workspaceId->value())
            ->where('email', strtolower(trim($email)))
            ->where('status', Invitation::STATUS_PENDING)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function listPendingByWorkspace(WorkspaceId $workspaceId): array
    {
        return InvitationModel::where('workspace_id', $workspaceId->value())
            ->where('status', Invitation::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (InvitationModel $model) => $this->toDomain($model))
            ->all();
    }

    private function toDomain(InvitationModel $model): Invitation
    {
        return new Invitation(
            id: new InvitationId((string) $model->id),
            workspaceId: new WorkspaceId((string) $model->workspace_id),
            email: (string) $model->email,
            role: MembershipRole::from((string) $model->role),
            tokenHash: (string) $model->token_hash,
            expiresAt: new DateTimeImmutable($model->expires_at->toIso8601String()),
            status: (string) $model->status,
            createdAt: new DateTimeImmutable($model->created_at->toIso8601String()),
            acceptedAt: $model->accepted_at !== null ? new DateTimeImmutable($model->accepted_at->toIso8601String()) : null,
        );
    }
}
