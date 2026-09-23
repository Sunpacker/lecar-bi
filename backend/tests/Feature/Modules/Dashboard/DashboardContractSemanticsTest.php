<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class DashboardContractSemanticsTest extends TestCase
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

    public function test_openapi_contract_does_not_leak_frontend_state(): void
    {
        $openapiPath = base_path('../contracts/openapi/analytics-v1.yaml');
        $this->assertFileExists($openapiPath);

        $content = (string) file_get_contents($openapiPath);
        $schema = Yaml::parse($content);
        $this->assertIsArray($schema);

        $widgetInputProps = array_keys($schema['components']['schemas']['WidgetInput']['properties']);
        $gridPosProps = array_keys($schema['components']['schemas']['WidgetGridPosition']['properties']);
        $queryConfigProps = array_keys($schema['components']['schemas']['WidgetQueryConfig']['properties']);

        // Assert strictly semantic properties only
        $this->assertEqualsCanonicalizing(['id', 'title', 'type', 'position', 'query_config', 'options'], $widgetInputProps);
        $this->assertEqualsCanonicalizing(['x', 'y', 'w', 'h'], $gridPosProps);
        $this->assertEqualsCanonicalizing(['dataset', 'metric', 'dimension', 'date_range'], $queryConfigProps);

        // Assert forbidden frontend-specific terms do NOT exist in widget schemas
        $forbiddenTerms = ['className', 'style', 'pixelWidth', 'pixelHeight', 'domId', 'component', 'handler'];
        foreach ($forbiddenTerms as $term) {
            $this->assertArrayNotHasKey($term, $schema['components']['schemas']['WidgetInput']['properties']);
            $this->assertArrayNotHasKey($term, $schema['components']['schemas']['WidgetGridPosition']['properties']);
            $this->assertArrayNotHasKey($term, $schema['components']['schemas']['WidgetQueryConfig']['properties']);
        }
    }

    public function test_api_ignores_and_sanitizes_spurious_frontend_fields(): void
    {
        // 1. Create empty dashboard
        $createRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Дашборд с виджетом',
            'description' => 'Проверка очистки спам-полей',
        ]);
        $createRes->assertStatus(201);
        $dashId = (string) $createRes->json('dashboard.id');

        // 2. Update with widget containing spurious frontend properties
        $updateRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->putJson("/api/v1/dashboards/{$dashId}", [
            'title' => 'Дашборд с виджетом',
            'description' => 'Проверка очистки спам-полей',
            'widgets' => [
                [
                    'id' => '00000000-0000-4000-8000-000000000001',
                    'title' => 'Семантический виджет',
                    'type' => 'kpi_card',
                    'className' => 'col-span-4 bg-red-500', // Spurious frontend state
                    'style' => 'width: 100px;',
                    'position' => [
                        'x' => 0,
                        'y' => 0,
                        'w' => 4,
                        'h' => 2,
                        'pixel_w' => 400, // Spurious
                    ],
                    'query_config' => [
                        'dataset' => 'sales',
                        'metric' => 'revenue',
                        'date_range' => '30d',
                    ],
                    'options' => [],
                ],
            ],
        ]);

        $updateRes->assertOk();
        $widget = $updateRes->json('dashboard.widgets.0');

        $this->assertArrayNotHasKey('className', $widget);
        $this->assertArrayNotHasKey('style', $widget);
        $this->assertArrayNotHasKey('pixel_w', $widget['position']);
        $this->assertSame('Семантический виджет', $widget['title']);
        $this->assertSame(4, $widget['position']['w']);

        // 3. Confirm GET response also contains only clean semantic data
        $getRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$dashId}");

        $getRes->assertOk();
        $getWidget = $getRes->json('dashboard.widgets.0');
        $this->assertArrayNotHasKey('className', $getWidget);
        $this->assertArrayNotHasKey('style', $getWidget);
        $this->assertArrayNotHasKey('pixel_w', $getWidget['position']);
        $this->assertEqualsCanonicalizing(['x', 'y', 'w', 'h'], array_keys($getWidget['position']));
    }

    public function test_cross_tenant_update_and_delete_enforces_ownership(): void
    {
        // 1. Create dashboard in ws-1
        $createRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Дашборд тенанта 1',
        ]);
        $dashId = $createRes->json('dashboard.id');

        // 2. User 2 (ws-2) attempting to update ws-1 dashboard returns 403
        $updateForbidden = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->putJson("/api/v1/dashboards/{$dashId}", [
            'title' => 'Взлом дашборда',
            'widgets' => [],
        ]);
        $updateForbidden->assertStatus(403);

        // 3. User 2 (ws-2) attempting to delete ws-1 dashboard returns 403
        $deleteForbidden = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->deleteJson("/api/v1/dashboards/{$dashId}");
        $deleteForbidden->assertStatus(403);
    }
}
