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
