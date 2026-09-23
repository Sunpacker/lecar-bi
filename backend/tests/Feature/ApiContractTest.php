<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

final class ApiContractTest extends TestCase
{
    public function test_health_endpoint_follows_the_published_contract(): void
    {
        $response = $this->getJson('/api/v1/health');
        $contract = $this->openApiContract();
        $schema = $this->healthResponseSchema($contract);

        $response->assertOk()->assertHeader('content-type', 'application/json');

        $payload = $response->json();

        self::assertIsArray($payload);
        $this->assertPayloadMatchesSchema($payload, $schema);
    }

    public function test_contract_contains_workspace_and_identity_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/auth/login', $contract['paths']);
        self::assertArrayHasKey('/me', $contract['paths']);
        self::assertArrayHasKey('/workspaces', $contract['paths']);
        self::assertArrayHasKey('/workspaces/{id}', $contract['paths']);
        self::assertArrayHasKey('/workspaces/current', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('LoginRequest', $schemas);
        self::assertArrayHasKey('LoginResponse', $schemas);
        self::assertArrayHasKey('UserResponse', $schemas);
        self::assertArrayHasKey('WorkspaceResponse', $schemas);
        self::assertArrayHasKey('WorkspaceListResponse', $schemas);
        self::assertArrayHasKey('CurrentWorkspaceResponse', $schemas);
        self::assertArrayHasKey('ErrorResponse', $schemas);
    }

    public function test_contract_contains_workspace_rbac_schemas_and_endpoints(): void
    {
        $contract = $this->openApiContract();
        $schemas = $contract['components']['schemas'];

        self::assertArrayHasKey('WorkspaceRole', $schemas);
        self::assertEqualsCanonicalizing(['owner', 'member', 'viewer'], $schemas['WorkspaceRole']['enum']);

        self::assertArrayHasKey('WorkspaceCapability', $schemas);
        $expectedCapabilities = [
            'analytics.view',
            'dashboards.view',
            'dashboards.manage',
            'imports.view',
            'imports.manage',
            'alerts.view',
            'alerts.manage',
            'workspace.members.manage',
        ];
        self::assertEqualsCanonicalizing($expectedCapabilities, $schemas['WorkspaceCapability']['enum']);

        self::assertArrayHasKey('WorkspaceMemberResponse', $schemas);
        self::assertArrayHasKey('WorkspaceMemberListResponse', $schemas);
        self::assertArrayHasKey('ChangeWorkspaceMemberRoleRequest', $schemas);
        self::assertArrayHasKey('ChangeWorkspaceMemberRoleResponse', $schemas);

        $workspaceSchema = $schemas['WorkspaceResponse'];
        self::assertContains('capabilities', $workspaceSchema['required']);
        self::assertArrayHasKey('capabilities', $workspaceSchema['properties']);

        self::assertArrayHasKey('/workspaces/{workspaceId}/members', $contract['paths']);
        self::assertArrayHasKey('get', $contract['paths']['/workspaces/{workspaceId}/members']);

        self::assertArrayHasKey('/workspaces/{workspaceId}/members/{userId}/role', $contract['paths']);
        self::assertArrayHasKey('patch', $contract['paths']['/workspaces/{workspaceId}/members/{userId}/role']);
    }

    public function test_protected_operations_declare_required_capabilities(): void
    {
        $contract = $this->openApiContract();
        $paths = $contract['paths'];

        $expectedMatrix = [
            '/analytics/sales/overview' => ['get' => 'analytics.view'],
            '/analytics/sales/filters' => ['get' => 'analytics.view'],
            '/analytics/sales/records' => ['get' => 'analytics.view'],
            '/analytics/inventory/summary' => ['get' => 'analytics.view'],
            '/analytics/inventory/items' => ['get' => 'analytics.view'],
            '/analytics/inventory/filters' => ['get' => 'analytics.view'],
            '/analytics/inventory/abc-xyz/summary' => ['get' => 'analytics.view'],
            '/analytics/inventory/abc-xyz/items' => ['get' => 'analytics.view'],
            '/analytics/suppliers/overview' => ['get' => 'analytics.view'],
            '/analytics/suppliers/filters' => ['get' => 'analytics.view'],
            '/analytics/suppliers/performance' => ['get' => 'analytics.view'],
            '/analytics/suppliers/deliveries' => ['get' => 'analytics.view'],
            '/dashboards' => [
                'get' => 'dashboards.view',
                'post' => 'dashboards.manage',
            ],
            '/dashboards/{id}' => [
                'get' => 'dashboards.view',
                'put' => 'dashboards.manage',
                'delete' => 'dashboards.manage',
            ],
            '/dashboards/{dashboardId}/views' => [
                'get' => 'dashboards.view',
                'post' => 'dashboards.manage',
            ],
            '/dashboards/{dashboardId}/views/{viewId}' => [
                'put' => 'dashboards.manage',
                'delete' => 'dashboards.manage',
            ],
            '/imports' => [
                'get' => 'imports.view',
                'post' => 'imports.manage',
            ],
            '/imports/{id}' => ['get' => 'imports.view'],
            '/imports/{id}/failures' => ['get' => 'imports.view'],
            '/imports/{id}/retry' => ['post' => 'imports.manage'],
            '/alert-rules' => [
                'get' => 'alerts.view',
                'post' => 'alerts.manage',
            ],
            '/alert-rules/{id}' => [
                'get' => 'alerts.view',
                'put' => 'alerts.manage',
                'delete' => 'alerts.manage',
            ],
            '/alert-rules/{id}/toggle' => ['post' => 'alerts.manage'],
            '/alert-rules/evaluate' => ['post' => 'alerts.manage'],
            '/alerts' => ['get' => 'alerts.view'],
            '/alerts/summary' => ['get' => 'alerts.view'],
            '/alerts/{id}' => ['get' => 'alerts.view'],
            '/alerts/{id}/acknowledge' => ['post' => 'alerts.manage'],
            '/alerts/{id}/resolve' => ['post' => 'alerts.manage'],
            '/workspaces/{workspaceId}/members' => ['get' => 'workspace.members.manage'],
            '/workspaces/{workspaceId}/members/{userId}/role' => ['patch' => 'workspace.members.manage'],
        ];

        foreach ($expectedMatrix as $path => $methods) {
            self::assertArrayHasKey($path, $paths, "Path {$path} missing from contract");
            foreach ($methods as $method => $expectedCap) {
                self::assertArrayHasKey($method, $paths[$path], "Method {$method} on {$path} missing");
                self::assertArrayHasKey(
                    'x-required-capability',
                    $paths[$path][$method],
                    "Operation {$method} {$path} missing x-required-capability"
                );
                self::assertSame(
                    $expectedCap,
                    $paths[$path][$method]['x-required-capability'],
                    "Operation {$method} {$path} has incorrect x-required-capability"
                );
            }
        }
    }

    public function test_contract_contains_sales_analytics_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/analytics/sales/overview', $contract['paths']);
        self::assertArrayHasKey('/analytics/sales/filters', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('SalesOverviewResponse', $schemas);
        self::assertArrayHasKey('SalesSummary', $schemas);
        self::assertArrayHasKey('SalesTrendPoint', $schemas);
        self::assertArrayHasKey('SalesCategoryBreakdown', $schemas);
        self::assertArrayHasKey('SalesRegionBreakdown', $schemas);
        self::assertArrayHasKey('SalesFilterOptionsResponse', $schemas);
    }

    public function test_contract_contains_inventory_intelligence_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/analytics/inventory/summary', $contract['paths']);
        self::assertArrayHasKey('/analytics/inventory/items', $contract['paths']);
        self::assertArrayHasKey('/analytics/inventory/filters', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('InventorySummaryResponse', $schemas);
        self::assertArrayHasKey('InventorySummary', $schemas);
        self::assertArrayHasKey('StockHealthBreakdownItem', $schemas);
        self::assertArrayHasKey('WarehouseStockBreakdownItem', $schemas);
        self::assertArrayHasKey('InventoryItemsResponse', $schemas);
        self::assertArrayHasKey('InventoryItem', $schemas);
        self::assertArrayHasKey('InventoryFilterOptionsResponse', $schemas);
    }

    public function test_contract_contains_abc_xyz_analysis_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/analytics/inventory/abc-xyz/summary', $contract['paths']);
        self::assertArrayHasKey('/analytics/inventory/abc-xyz/items', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('AbcXyzSummaryResponse', $schemas);
        self::assertArrayHasKey('AbcXyzSummary', $schemas);
        self::assertArrayHasKey('AbcXyzMatrixCell', $schemas);
        self::assertArrayHasKey('AbcDistributionItem', $schemas);
        self::assertArrayHasKey('XyzDistributionItem', $schemas);
        self::assertArrayHasKey('AbcXyzItemsResponse', $schemas);
        self::assertArrayHasKey('AbcXyzProductItem', $schemas);
    }

    public function test_contract_contains_dashboard_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/dashboards', $contract['paths']);
        self::assertArrayHasKey('/dashboards/{id}', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('DashboardListResponse', $schemas);
        self::assertArrayHasKey('DashboardSummary', $schemas);
        self::assertArrayHasKey('DashboardDetailResponse', $schemas);
        self::assertArrayHasKey('DashboardDetail', $schemas);
        self::assertArrayHasKey('WidgetDetail', $schemas);
        self::assertArrayHasKey('WidgetInput', $schemas);
        self::assertArrayHasKey('WidgetGridPosition', $schemas);
        self::assertArrayHasKey('WidgetQueryConfig', $schemas);
        self::assertArrayHasKey('CreateDashboardRequest', $schemas);
        self::assertArrayHasKey('UpdateDashboardRequest', $schemas);
    }

    public function test_contract_contains_saved_views_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/dashboards/{dashboardId}/views', $contract['paths']);
        self::assertArrayHasKey('/dashboards/{dashboardId}/views/{viewId}', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('DashboardFilterValues', $schemas);
        self::assertArrayHasKey('DashboardSavedView', $schemas);
        self::assertArrayHasKey('DashboardSavedViewListResponse', $schemas);
        self::assertArrayHasKey('DashboardSavedViewResponse', $schemas);
        self::assertArrayHasKey('CreateDashboardSavedViewRequest', $schemas);
        self::assertArrayHasKey('UpdateDashboardSavedViewRequest', $schemas);
    }

    public function test_contract_contains_data_ingestion_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/imports', $contract['paths']);
        self::assertArrayHasKey('/imports/{id}', $contract['paths']);
        self::assertArrayHasKey('/imports/{id}/failures', $contract['paths']);
        self::assertArrayHasKey('/imports/{id}/retry', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('ImportBatchSummary', $schemas);
        self::assertArrayHasKey('ImportBatchListResponse', $schemas);
        self::assertArrayHasKey('ImportBatchDetail', $schemas);
        self::assertArrayHasKey('ImportBatchDetailResponse', $schemas);
        self::assertArrayHasKey('ImportFailureItem', $schemas);
        self::assertArrayHasKey('ImportFailureListResponse', $schemas);
    }

    public function test_contract_contains_supplier_analytics_endpoints(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/analytics/suppliers/overview', $contract['paths']);
        self::assertArrayHasKey('/analytics/suppliers/filters', $contract['paths']);
        self::assertArrayHasKey('/analytics/suppliers/performance', $contract['paths']);
        self::assertArrayHasKey('/analytics/suppliers/deliveries', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('SupplierOverviewResponse', $schemas);
        self::assertArrayHasKey('SupplierSummary', $schemas);
        self::assertArrayHasKey('DeliveryStatusBreakdownItem', $schemas);
        self::assertArrayHasKey('SupplierTrendPoint', $schemas);
        self::assertArrayHasKey('SupplierPerformanceResponse', $schemas);
        self::assertArrayHasKey('SupplierPerformanceItem', $schemas);
        self::assertArrayHasKey('SupplierDeliveriesResponse', $schemas);
        self::assertArrayHasKey('SupplierDeliveryItem', $schemas);
        self::assertArrayHasKey('SupplierFilterOptionsResponse', $schemas);
        self::assertArrayHasKey('DeliveryStatus', $schemas);
        self::assertArrayHasKey('SupplierReliabilityTier', $schemas);
    }

    public function test_contract_contains_alerting_endpoints_and_schemas(): void
    {
        $contract = $this->openApiContract();

        self::assertArrayHasKey('/alert-rules', $contract['paths']);
        self::assertArrayHasKey('/alert-rules/{id}', $contract['paths']);
        self::assertArrayHasKey('/alert-rules/{id}/toggle', $contract['paths']);
        self::assertArrayHasKey('/alert-rules/evaluate', $contract['paths']);
        self::assertArrayHasKey('/alerts', $contract['paths']);
        self::assertArrayHasKey('/alerts/summary', $contract['paths']);
        self::assertArrayHasKey('/alerts/{id}', $contract['paths']);
        self::assertArrayHasKey('/alerts/{id}/acknowledge', $contract['paths']);
        self::assertArrayHasKey('/alerts/{id}/resolve', $contract['paths']);

        $schemas = $contract['components']['schemas'];
        self::assertArrayHasKey('AlertRule', $schemas);
        self::assertArrayHasKey('AlertRuleListResponse', $schemas);
        self::assertArrayHasKey('AlertRuleDetailResponse', $schemas);
        self::assertArrayHasKey('CreateAlertRuleRequest', $schemas);
        self::assertArrayHasKey('UpdateAlertRuleRequest', $schemas);
        self::assertArrayHasKey('Alert', $schemas);
        self::assertArrayHasKey('AlertListResponse', $schemas);
        self::assertArrayHasKey('AlertDetailResponse', $schemas);
        self::assertArrayHasKey('AlertSummaryResponse', $schemas);
        self::assertArrayHasKey('AlertEvaluationResultResponse', $schemas);
        self::assertArrayHasKey('AlertSeverity', $schemas);
        self::assertArrayHasKey('AlertStatus', $schemas);
        self::assertArrayHasKey('RuleType', $schemas);
    }

    /** @return array<string, mixed> */
    private function openApiContract(): array
    {
        $contract = Yaml::parseFile(dirname(__DIR__, 3).'/contracts/openapi/analytics-v1.yaml');

        self::assertIsArray($contract);

        return $contract;
    }

    /**
     * @param  array<string, mixed>  $contract
     * @return array<string, mixed>
     */
    private function healthResponseSchema(array $contract): array
    {
        $schemaReference = $contract['paths']['/health']['get']['responses']['200']['content']['application/json']['schema']['$ref'];

        self::assertSame('#/components/schemas/HealthResponse', $schemaReference);

        $schema = $contract['components']['schemas']['HealthResponse'];
        self::assertIsArray($schema);

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schema
     */
    private function assertPayloadMatchesSchema(array $payload, array $schema): void
    {
        self::assertFalse($schema['additionalProperties']);
        self::assertEqualsCanonicalizing($schema['required'], array_keys($payload));

        foreach ($schema['properties'] as $property => $definition) {
            self::assertIsString($payload[$property]);
            self::assertContains($payload[$property], $definition['enum']);
        }
    }
}
