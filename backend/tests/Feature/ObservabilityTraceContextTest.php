<?php

namespace Tests\Feature;

use App\Modules\Alerting\Application\Mappers\AlertTriggeredIntegrationMapper;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Events\AlertTriggered;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Shared\Domain\DomainEventId;
use App\Shared\Infrastructure\Logging\JsonLogFormatter;
use DateTimeImmutable;
use Illuminate\Support\Facades\Context;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

final class ObservabilityTraceContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    public function test_health_endpoint_echoes_request_and_correlation_headers(): void
    {
        $requestId = 'req-custom-trace-123';
        $correlationId = 'corr-custom-trace-456';

        $response = $this->withHeaders([
            'X-Request-Id' => $requestId,
            'X-Correlation-Id' => $correlationId,
        ])->getJson('/api/v1/health');

        $response->assertOk()
            ->assertHeader('X-Request-Id', $requestId)
            ->assertHeader('X-Correlation-Id', $correlationId);
    }

    public function test_health_endpoint_generates_request_and_correlation_ids_when_missing(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        $generatedRequestId = $response->headers->get('X-Request-Id');
        $generatedCorrelationId = $response->headers->get('X-Correlation-Id');

        self::assertNotNull($generatedRequestId);
        self::assertNotNull($generatedCorrelationId);
        self::assertSame($generatedRequestId, $generatedCorrelationId);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $generatedRequestId
        );
    }

    public function test_outbox_integration_event_envelope_carries_correlation_id_from_context(): void
    {
        Context::flush();
        Context::add('request_id', 'req-job-trace-789');
        Context::add('correlation_id', 'corr-origin-trace-999');

        $domainEvent = new AlertTriggered(
            eventId: new DomainEventId('evt-trace-001'),
            alertId: new AlertId('alt-trace-001'),
            workspaceId: '00000000-0000-0000-0000-000000000001',
            ruleId: new AlertRuleId('rule-trace-001'),
            ruleName: 'Проверка трассировки',
            severity: AlertSeverity::WARNING,
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            currentValue: 3.0,
            thresholdValue: 10.0,
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад',
                productId: 'prod-1',
                productName: 'Тормозные колодки',
                productSku: 'BRK-001',
                currentValue: 3.0,
                thresholdValue: 10.0,
            ),
            occurredAt: new DateTimeImmutable('2026-09-25T10:00:00+00:00'),
        );

        $mapper = new AlertTriggeredIntegrationMapper;
        $integration = $mapper->map($domainEvent);
        $envelope = $integration->toEnvelope();

        self::assertSame('corr-origin-trace-999', $envelope['correlation_id']);
        self::assertSame('alert.triggered', $envelope['event_type']);
        self::assertSame(1, $envelope['event_version']);
    }

    public function test_json_log_formatter_includes_trace_context_and_redacts_sensitive_keys(): void
    {
        Context::flush();
        Context::add('request_id', 'req-log-trace-111');
        Context::add('correlation_id', 'corr-log-trace-222');
        Context::add('workspace_id', 'ws-sample-333');

        $formatter = new JsonLogFormatter;
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-09-25T12:00:00Z'),
            channel: 'testing',
            level: Level::Info,
            message: 'User authentication succeeded',
            context: [
                'user_id' => 'usr-123',
                'password' => 'supersecret',
                'api_key' => 'token-key-abc',
            ],
            extra: [
                'ip' => '127.0.0.1',
            ]
        );

        $jsonString = $formatter->format($record);
        $decoded = json_decode($jsonString, true);

        self::assertIsArray($decoded);
        self::assertSame('User authentication succeeded', $decoded['message']);
        self::assertSame('INFO', $decoded['level']);
        self::assertSame('req-log-trace-111', $decoded['request_id']);
        self::assertSame('corr-log-trace-222', $decoded['correlation_id']);
        self::assertSame('ws-sample-333', $decoded['context']['workspace_id']);
        self::assertSame('usr-123', $decoded['context']['user_id']);
        self::assertSame('[REDACTED]', $decoded['context']['password']);
        self::assertSame('[REDACTED]', $decoded['context']['api_key']);
    }
}
