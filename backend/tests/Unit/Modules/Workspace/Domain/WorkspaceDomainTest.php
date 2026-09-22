<?php

namespace Tests\Unit\Modules\Workspace\Domain;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
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
}
