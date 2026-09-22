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
