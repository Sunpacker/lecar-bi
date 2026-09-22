<?php

namespace App\Modules\Workspace\Domain;

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

    public function memberRole(UserId $userId): ?MembershipRole
    {
        if (! isset($this->memberships[$userId->value()])) {
            return null;
        }

        return $this->memberships[$userId->value()]->role();
    }

    /** @return list<Membership> */
    public function memberships(): array
    {
        return array_values($this->memberships);
    }
}
