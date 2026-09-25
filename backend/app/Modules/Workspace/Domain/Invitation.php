<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

final class Invitation
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly InvitationId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly string $email,
        private readonly MembershipRole $role,
        private string $tokenHash,
        private \DateTimeImmutable $expiresAt,
        private string $status = self::STATUS_PENDING,
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable('now'),
        private ?\DateTimeImmutable $acceptedAt = null,
    ) {}

    public function id(): InvitationId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function role(): MembershipRole
    {
        return $this->role;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function acceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function accept(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException('Only pending invitations can be accepted.');
        }

        if ($this->isExpired($now)) {
            throw new \DomainException('Invitation has expired.');
        }

        $this->status = self::STATUS_ACCEPTED;
        $this->acceptedAt = $now;
    }

    public function cancel(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException('Only pending invitations can be cancelled.');
        }

        $this->status = self::STATUS_CANCELLED;
    }

    public function renew(string $newTokenHash, \DateTimeImmutable $newExpiresAt): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException('Only pending invitations can be renewed.');
        }

        $this->tokenHash = $newTokenHash;
        $this->expiresAt = $newExpiresAt;
    }
}
