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
