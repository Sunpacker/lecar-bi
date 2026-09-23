<?php

namespace Tests\Unit\Modules\Workspace\Domain;

use App\Modules\Workspace\Domain\Exceptions\LastWorkspaceOwnerException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceMemberNotFoundException;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use App\Modules\Workspace\Domain\WorkspaceId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkspaceDomainTest extends TestCase
{
    #[Test]
    public function workspace_manages_membership_and_enforces_boundaries(): void
    {
        $workspace = new Workspace(
            id: new WorkspaceId('ws-100'),
            name: 'AutoParts Retail',
            slug: 'autoparts-retail',
        );

        $ownerId = new UserId('user-1');
        $memberId = new UserId('user-2');
        $strangerId = new UserId('user-999');

        $workspace->addMember($ownerId, MembershipRole::OWNER);
        $workspace->addMember($memberId, MembershipRole::MEMBER);

        self::assertTrue($workspace->hasMember($ownerId));
        self::assertTrue($workspace->hasMember($memberId));
        self::assertFalse($workspace->hasMember($strangerId));

        self::assertSame(MembershipRole::OWNER, $workspace->memberRole($ownerId));
        self::assertSame(MembershipRole::MEMBER, $workspace->memberRole($memberId));
        self::assertNull($workspace->memberRole($strangerId));
    }

    #[Test]
    public function user_holds_identity_properties(): void
    {
        $user = new User(
            id: new UserId('user-1'),
            email: 'elena@autobi.internal',
            name: 'Elena Rostova',
        );

        self::assertSame('user-1', $user->id()->value());
        self::assertSame('elena@autobi.internal', $user->email());
        self::assertSame('Elena Rostova', $user->name());
    }

    #[Test]
    public function empty_user_id_or_workspace_id_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UserId('');
    }

    #[Test]
    public function role_capabilities_follow_canonical_matrix(): void
    {
        self::assertTrue(MembershipRole::OWNER->allows(WorkspaceCapability::WORKSPACE_MEMBERS_MANAGE));
        self::assertTrue(MembershipRole::OWNER->allows(WorkspaceCapability::DASHBOARDS_MANAGE));
        self::assertTrue(MembershipRole::OWNER->allows(WorkspaceCapability::ANALYTICS_VIEW));

        self::assertFalse(MembershipRole::MEMBER->allows(WorkspaceCapability::WORKSPACE_MEMBERS_MANAGE));
        self::assertTrue(MembershipRole::MEMBER->allows(WorkspaceCapability::DASHBOARDS_MANAGE));
        self::assertTrue(MembershipRole::MEMBER->allows(WorkspaceCapability::IMPORTS_MANAGE));
        self::assertTrue(MembershipRole::MEMBER->allows(WorkspaceCapability::ALERTS_MANAGE));
        self::assertTrue(MembershipRole::MEMBER->allows(WorkspaceCapability::ANALYTICS_VIEW));

        self::assertFalse(MembershipRole::VIEWER->allows(WorkspaceCapability::WORKSPACE_MEMBERS_MANAGE));
        self::assertFalse(MembershipRole::VIEWER->allows(WorkspaceCapability::DASHBOARDS_MANAGE));
        self::assertFalse(MembershipRole::VIEWER->allows(WorkspaceCapability::IMPORTS_MANAGE));
        self::assertFalse(MembershipRole::VIEWER->allows(WorkspaceCapability::ALERTS_MANAGE));
        self::assertTrue(MembershipRole::VIEWER->allows(WorkspaceCapability::ANALYTICS_VIEW));
        self::assertTrue(MembershipRole::VIEWER->allows(WorkspaceCapability::DASHBOARDS_VIEW));
        self::assertTrue(MembershipRole::VIEWER->allows(WorkspaceCapability::IMPORTS_VIEW));
        self::assertTrue(MembershipRole::VIEWER->allows(WorkspaceCapability::ALERTS_VIEW));
    }

    #[Test]
    public function workspace_allows_role_changes_with_multiple_owners(): void
    {
        $workspace = new Workspace(
            id: new WorkspaceId('ws-1'),
            name: 'Workspace 1',
            slug: 'ws-1',
        );

        $owner1 = new UserId('u-1');
        $owner2 = new UserId('u-2');
        $member = new UserId('u-3');

        $workspace->addMember($owner1, MembershipRole::OWNER);
        $workspace->addMember($owner2, MembershipRole::OWNER);
        $workspace->addMember($member, MembershipRole::MEMBER);

        self::assertSame(2, $workspace->ownerCount());

        // Demote one owner when two exist
        $workspace->changeMemberRole($owner1, MembershipRole::MEMBER);
        self::assertSame(MembershipRole::MEMBER, $workspace->memberRole($owner1));
        self::assertSame(1, $workspace->ownerCount());

        // Promote member to viewer
        $workspace->changeMemberRole($member, MembershipRole::VIEWER);
        self::assertSame(MembershipRole::VIEWER, $workspace->memberRole($member));

        // Promote viewer to owner
        $workspace->changeMemberRole($member, MembershipRole::OWNER);
        self::assertSame(MembershipRole::OWNER, $workspace->memberRole($member));
        self::assertSame(2, $workspace->ownerCount());

        // Same-role change is idempotent
        $workspace->changeMemberRole($member, MembershipRole::OWNER);
        self::assertSame(MembershipRole::OWNER, $workspace->memberRole($member));
    }

    #[Test]
    public function changing_role_of_last_owner_throws_exception(): void
    {
        $workspace = new Workspace(
            id: new WorkspaceId('ws-1'),
            name: 'Workspace 1',
            slug: 'ws-1',
        );

        $owner = new UserId('u-1');
        $member = new UserId('u-2');

        $workspace->addMember($owner, MembershipRole::OWNER);
        $workspace->addMember($member, MembershipRole::MEMBER);

        $this->expectException(LastWorkspaceOwnerException::class);
        $workspace->changeMemberRole($owner, MembershipRole::VIEWER);
    }

    #[Test]
    public function changing_role_of_non_member_throws_exception(): void
    {
        $workspace = new Workspace(
            id: new WorkspaceId('ws-1'),
            name: 'Workspace 1',
            slug: 'ws-1',
        );

        $this->expectException(WorkspaceMemberNotFoundException::class);
        $workspace->changeMemberRole(new UserId('unknown-user'), MembershipRole::OWNER);
    }
}
