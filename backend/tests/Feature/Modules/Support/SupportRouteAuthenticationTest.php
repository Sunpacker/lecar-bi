<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Support;

use Tests\TestCase;

final class SupportRouteAuthenticationTest extends TestCase
{
    public function test_support_requires_authentication(): void
    {
        $this->getJson('/api/v1/support/conversations')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_forged_browser_identity_is_rejected_without_trusted_transport_credential(): void
    {
        config(['support.bff_shared_secret' => 'server-only-secret-with-at-least-32-characters']);

        $this->withHeaders([
            'X-User-Id' => 'victim-user-id',
            'X-Workspace-Id' => 'victim-workspace-id',
        ])->getJson('/api/v1/support/conversations')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->withHeaders([
            'X-User-Id' => 'victim-user-id',
            'X-Workspace-Id' => 'victim-workspace-id',
            'X-Support-BFF-Key' => 'attacker-controlled-value',
        ])->getJson('/api/v1/support/conversations')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}
