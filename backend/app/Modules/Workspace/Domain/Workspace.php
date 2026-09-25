<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

use App\Modules\Workspace\Domain\Exceptions\LastWorkspaceOwnerException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceMemberNotFoundException;

final class Workspace
{
    /** @var array<string, Membership> */
    private array $memberships = [];

    public function __construct(
        private readonly WorkspaceId $id,
        private string $name,
        private string $slug,
    ) {}

    public function id(): WorkspaceId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Workspace name cannot be empty.');
        }

        $this->name = $trimmed;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function addMember(UserId $userId, MembershipRole $role): void
    {
        $this->memberships[$userId->value()] = new Membership($this->id, $userId, $role);
    }

    public function hasMember(UserId $userId): bool
    {
        return array_key_exists($userId->value(), $this->memberships);
    }

    public function member(UserId $userId): ?Membership
    {
        return $this->memberships[$userId->value()] ?? null;
    }

    public function memberRole(UserId $userId): ?MembershipRole
    {
        if (! isset($this->memberships[$userId->value()])) {
            return null;
        }

        return $this->memberships[$userId->value()]->role();
    }

    public function ownerCount(): int
    {
        $count = 0;
        foreach ($this->memberships as $membership) {
            if ($membership->role() === MembershipRole::OWNER) {
                $count++;
            }
        }

        return $count;
    }

    public function changeMemberRole(UserId $userId, MembershipRole $newRole): void
    {
        $membership = $this->member($userId);
        if ($membership === null) {
            throw new WorkspaceMemberNotFoundException($this->id, $userId);
        }

        if ($membership->role() === $newRole) {
            return;
        }

        if ($membership->role() === MembershipRole::OWNER && $newRole !== MembershipRole::OWNER) {
            if ($this->ownerCount() <= 1) {
                throw new LastWorkspaceOwnerException($this->id, $userId);
            }
        }

        $this->memberships[$userId->value()] = $membership->changeRole($newRole);
    }

    /** @return list<Membership> */
    public function memberships(): array
    {
        return array_values($this->memberships);
    }
}
