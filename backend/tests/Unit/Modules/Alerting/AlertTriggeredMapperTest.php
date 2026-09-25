<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Alerting;

use App\Modules\Alerting\Application\Mappers\AlertTriggeredIntegrationMapper;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Events\AlertTriggered;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Shared\Application\IntegrationEvent;
use App\Shared\Domain\DomainEventId;
use DateTimeImmutable;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

/**
 * Tests that AlertTriggeredIntegrationMapper produces an envelope
 * conforming to contracts/events/alert-triggered.v1.schema.json.
 */
final class AlertTriggeredMapperTest extends TestCase
{
    private AlertTriggeredIntegrationMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new AlertTriggeredIntegrationMapper;
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    public function test_maps_domain_event_to_integration_event_with_correct_envelope_structure(): void
    {
        $event = $this->createFixedAlertTriggered();

        $integration = $this->mapper->map($event);

        self::assertInstanceOf(IntegrationEvent::class, $integration);
        self::assertSame('evt-fixed-001', $integration->eventId);
        self::assertSame('alert.triggered', $integration->eventType);
        self::assertSame(1, $integration->eventVersion);
        self::assertSame('analytics', $integration->producer);
        self::assertSame('ws-abc', $integration->workspaceId);
    }

    public function test_maps_correlation_id_from_context_when_available(): void
    {
        Context::add('correlation_id', 'corr-ctx-12345');

        $integration = $this->mapper->map($this->createFixedAlertTriggered());
        $envelope = $integration->toEnvelope();

        self::assertSame('corr-ctx-12345', $integration->correlationId);
        self::assertSame('corr-ctx-12345', $envelope['correlation_id']);

        Context::flush();
    }

    public function test_maps_aggregate_correctly(): void
    {
        $integration = $this->mapper->map($this->createFixedAlertTriggered());

        self::assertSame(['type' => 'alert', 'id' => 'alt-xyz'], $integration->aggregate);
    }

    public function test_maps_payload_fields(): void
    {
        $integration = $this->mapper->map($this->createFixedAlertTriggered());
        $payload = $integration->payload;

        self::assertSame('rule-123', $payload['rule_id']);
        self::assertSame('Дефицит запасов', $payload['rule_name']);
        self::assertSame('critical', $payload['severity']);
        self::assertSame('quantity_available', $payload['metric']);
        self::assertSame('lte', $payload['comparator']);
        self::assertSame(5.0, $payload['current_value']);
        self::assertSame(10.0, $payload['threshold_value']);
    }

    public function test_maps_analytical_context(): void
    {
        $integration = $this->mapper->map($this->createFixedAlertTriggered());
        $context = $integration->payload['analytical_context'];

        self::assertSame('inventory', $context['target']);
        self::assertSame('wh-1', $context['warehouse_id']);
        self::assertSame('Центральный склад', $context['warehouse_name']);
        self::assertSame('prod-1', $context['product_id']);
        self::assertSame('Тормозные колодки', $context['product_name']);
        self::assertSame('BRK-001', $context['product_sku']);
    }

    public function test_maps_occurred_at_as_iso8601(): void
    {
        $integration = $this->mapper->map($this->createFixedAlertTriggered());

        // Verify it's a valid ISO 8601 date-time string
        self::assertNotEmpty($integration->occurredAt);
        self::assertNotFalse(DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $integration->occurredAt));
    }

    public function test_maps_null_rule_id(): void
    {
        $event = new AlertTriggered(
            eventId: new DomainEventId('evt-fixed-002'),
            alertId: new AlertId('alt-xyz'),
            workspaceId: 'ws-abc',
            ruleId: null,
            ruleName: 'Orphan Rule',
            severity: AlertSeverity::INFO,
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            currentValue: 0.0,
            thresholdValue: 0.0,
            context: new AlertContext(target: 'inventory'),
            occurredAt: new DateTimeImmutable('2026-09-23T10:00:00+00:00'),
        );

        $integration = $this->mapper->map($event);

        self::assertNull($integration->payload['rule_id']);
    }

    public function test_envelope_contains_all_required_schema_fields(): void
    {
        $integration = $this->mapper->map($this->createFixedAlertTriggered());
        $envelope = $integration->toEnvelope();

        $requiredFields = ['event_id', 'event_type', 'event_version', 'occurred_at', 'producer', 'workspace_id', 'aggregate', 'payload'];

        foreach ($requiredFields as $field) {
            self::assertArrayHasKey($field, $envelope, "Missing required field: {$field}");
        }

        // Payload required fields per schema
        $requiredPayloadFields = ['rule_id', 'rule_name', 'severity', 'metric', 'comparator', 'current_value', 'threshold_value', 'analytical_context'];

        foreach ($requiredPayloadFields as $field) {
            self::assertArrayHasKey($field, $envelope['payload'], "Missing required payload field: {$field}");
        }

        // Analytical context required fields
        self::assertArrayHasKey('target', $envelope['payload']['analytical_context']);
    }

    private function createFixedAlertTriggered(): AlertTriggered
    {
        return new AlertTriggered(
            eventId: new DomainEventId('evt-fixed-001'),
            alertId: new AlertId('alt-xyz'),
            workspaceId: 'ws-abc',
            ruleId: new AlertRuleId('rule-123'),
            ruleName: 'Дефицит запасов',
            severity: AlertSeverity::CRITICAL,
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            currentValue: 5.0,
            thresholdValue: 10.0,
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад',
                productId: 'prod-1',
                productName: 'Тормозные колодки',
                productSku: 'BRK-001',
                currentValue: 5.0,
                thresholdValue: 10.0,
            ),
            occurredAt: new DateTimeImmutable('2026-09-23T10:00:00+00:00'),
        );
    }
}
