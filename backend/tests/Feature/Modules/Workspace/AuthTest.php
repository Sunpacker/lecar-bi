<?php

namespace Tests\Feature\Modules\Workspace;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $user2 = new User(new UserId('user-2'), 'dmitry@autobi.internal', 'Dmitry Smirnov');
        $userRepo->save($user1);
        $userRepo->save($user2);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);
        $wsRepo->save($ws2);
    }

    public function test_successful_login_returns_user_data(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'elena@autobi.internal',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', 'user-1')
            ->assertJsonPath('user.email', 'elena@autobi.internal')
            ->assertJsonPath('user.name', 'Elena Rostova');
    }

    public function test_login_for_second_user_returns_user_2(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'dmitry@autobi.internal',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', 'user-2')
            ->assertJsonPath('user.email', 'dmitry@autobi.internal')
            ->assertJsonPath('user.name', 'Dmitry Smirnov');
    }

    public function test_login_with_incorrect_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'elena@autobi.internal',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid email or password.',
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@autobi.internal',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid email or password.',
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_login_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
