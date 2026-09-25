<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Workspace;

use App\Modules\Workspace\Application\Commands\CreateInvitationCommand;
use App\Modules\Workspace\Application\Commands\CreateInvitationHandler;
use App\Modules\Workspace\Domain\Invitation;
use App\Modules\Workspace\Domain\InvitationId;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SettingsAndInvitationsTest extends TestCase
{
    private UserRepositoryInterface $userRepo;

    private WorkspaceRepositoryInterface $wsRepo;

    private InvitationRepositoryInterface $invRepo;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();

        $this->userRepo = $this->app->make(UserRepositoryInterface::class);
        $this->wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->invRepo = $this->app->make(InvitationRepositoryInterface::class);

        // Owner
        $owner = new User(
            id: new UserId('user-1'),
            email: 'elena@autobi.internal',
            name: 'Elena Rostova',
            passwordHash: password_hash('password123', PASSWORD_DEFAULT),
        );
        $this->userRepo->save($owner);

        // Member
        $member = new User(
            id: new UserId('user-2'),
            email: 'dmitry@autobi.internal',
            name: 'Dmitry Smirnov',
            passwordHash: password_hash('password123', PASSWORD_DEFAULT),
        );
        $this->userRepo->save($member);

        // Workspace
        $ws = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $ws->addMember(new UserId('user-2'), MembershipRole::MEMBER);
        $this->wsRepo->save($ws);
    }

    public function test_user_can_update_profile_name(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->patchJson('/api/v2/me', [
                'name' => 'Elena Super-Admin',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Elena Super-Admin')
            ->assertJsonPath('email', 'elena@autobi.internal');

        $user = $this->userRepo->findById(new UserId('user-1'));
        $this->assertNotNull($user);
        $this->assertSame('Elena Super-Admin', $user->name());
    }

    public function test_user_can_change_password_and_login_with_new_password(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->postJson('/api/v2/me/password', [
                'current_password' => 'password123',
                'new_password' => 'new-secure-password',
                'new_password_confirmation' => 'new-secure-password',
            ]);

        $response->assertStatus(204);

        // Old password fails
        $failedLogin = $this->postJson('/api/v2/auth/login', [
            'email' => 'elena@autobi.internal',
            'password' => 'password123',
        ]);
        $failedLogin->assertStatus(401);

        // New password works
        $loginResponse = $this->postJson('/api/v2/auth/login', [
            'email' => 'elena@autobi.internal',
            'password' => 'new-secure-password',
        ]);
        $loginResponse->assertStatus(200)
            ->assertJsonPath('user.email', 'elena@autobi.internal');
    }

    public function test_change_password_with_incorrect_current_password_returns_401(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->postJson('/api/v2/me/password', [
                'current_password' => 'wrong-current-pass',
                'new_password' => 'new-password-12345',
                'new_password_confirmation' => 'new-password-12345',
            ]);

        $response->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_owner_can_rename_workspace(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->patchJson('/api/v2/workspaces/ws-1', [
            'name' => 'AutoParts Global Holding',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'AutoParts Global Holding');

        $ws = $this->wsRepo->findById(new WorkspaceId('ws-1'));
        $this->assertNotNull($ws);
        $this->assertSame('AutoParts Global Holding', $ws->name());
    }

    public function test_member_cannot_rename_workspace_due_to_missing_capability(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-1',
        ])->patchJson('/api/v2/workspaces/ws-1', [
            'name' => 'Hacked Workspace Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_invite_member_and_receive_list(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v2/workspaces/ws-1/invitations', [
            'email' => 'newuser@example.com',
            'role' => 'member',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('email', 'newuser@example.com')
            ->assertJsonPath('role', 'member')
            ->assertJsonPath('status', 'pending');

        $invitationId = $response->json('id');

        // List invitations
        $listResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v2/workspaces/ws-1/invitations');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $invitationId);

        // Resend
        $resendResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v2/workspaces/ws-1/invitations/{$invitationId}/resend");

        $resendResponse->assertStatus(200)
            ->assertJsonPath('id', $invitationId);

        // Cancel
        $deleteResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->deleteJson("/api/v2/workspaces/ws-1/invitations/{$invitationId}");

        $deleteResponse->assertStatus(204);
    }

    public function test_public_invitation_flow_and_accept_for_new_user(): void
    {
        // 1. Create invitation directly via handler
        $createHandler = $this->app->make(CreateInvitationHandler::class);
        $dto = $createHandler->handle(new CreateInvitationCommand(
            workspaceId: 'ws-1',
            email: 'partner@example.com',
            role: MembershipRole::VIEWER,
        ));

        // Find the raw token from the invitation in repo
        $invitation = $this->invRepo->findById(new InvitationId($dto->id));
        $this->assertNotNull($invitation);

        // In tests we know token hash, but we need a raw token.
        // Let's create an invitation with a known raw token directly:
        $rawToken = 'raw-test-token-12345';
        $tokenHash = hash('sha256', $rawToken);
        $knownInv = new Invitation(
            id: new InvitationId('inv-test-known'),
            workspaceId: new WorkspaceId('ws-1'),
            email: 'newbie@example.com',
            role: MembershipRole::MEMBER,
            tokenHash: $tokenHash,
            expiresAt: new \DateTimeImmutable('+7 days'),
        );
        $this->invRepo->save($knownInv);

        // 2. View public details without authentication
        $detailsResponse = $this->getJson("/api/v2/invitations/{$rawToken}");
        $detailsResponse->assertStatus(200)
            ->assertJsonPath('email', 'newbie@example.com')
            ->assertJsonPath('workspace_name', 'AutoParts Retail')
            ->assertJsonPath('role', 'member')
            ->assertJsonPath('is_expired', false)
            ->assertJsonPath('is_existing_user', false);

        // 3. Accept invitation
        $acceptResponse = $this->postJson("/api/v2/invitations/{$rawToken}/accept", [
            'name' => 'Newbie User',
            'password' => 'secret-password-123',
        ]);

        $acceptResponse->assertStatus(200)
            ->assertJsonPath('user.email', 'newbie@example.com')
            ->assertJsonPath('user.name', 'Newbie User')
            ->assertJsonPath('workspace_id', 'ws-1')
            ->assertJsonStructure(['token']);

        // 4. Verify user was added to workspace
        $ws = $this->wsRepo->findById(new WorkspaceId('ws-1'));
        $this->assertNotNull($ws);
        $newUserId = $acceptResponse->json('user.id');
        $this->assertTrue($ws->hasMember(new UserId($newUserId)));

        // 5. Trying to accept again fails
        $secondAccept = $this->postJson("/api/v2/invitations/{$rawToken}/accept");
        $secondAccept->assertStatus(400);
    }
}
