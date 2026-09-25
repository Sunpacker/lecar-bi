<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Notification;

use InvalidArgumentException;
use NotificationService\Notification\Application\AlertTriggeredV1Decoder;
use NotificationService\Notification\Domain\NotificationSeverity;
use PHPUnit\Framework\TestCase;

final class AlertTriggeredV1DecoderTest extends TestCase
{
    private AlertTriggeredV1Decoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decoder = new AlertTriggeredV1Decoder;
    }

    public function test_decodes_valid_event_array(): void
    {
        $data = $this->validEventData();

        $event = $this->decoder->decode($data);

        $this->assertSame('evt-12345', $event->eventId);
        $this->assertSame('alert.triggered', $event->eventType);
        $this->assertSame(1, $event->eventVersion);
        $this->assertSame('analytics', $event->producer);
        $this->assertSame('ws-1', $event->workspaceId);
        $this->assertSame('alt-1', $event->alertId);
        $this->assertSame('rule-1', $event->ruleId);
        $this->assertSame('Low Stock', $event->ruleName);
        $this->assertSame(NotificationSeverity::CRITICAL, $event->severity);
        $this->assertSame('quantity_available', $event->metric);
        $this->assertSame('lte', $event->comparator);
        $this->assertSame(5.0, $event->currentValue);
        $this->assertSame(10.0, $event->thresholdValue);
        $this->assertSame(['target' => 'inventory', 'sku' => 'SKU-1'], $event->analyticalContext);
        $this->assertSame('2026-09-23T10:00:00+00:00', $event->occurredAt->format(\DateTimeInterface::ATOM));
    }

    public function test_decodes_valid_event_json_string(): void
    {
        $json = json_encode($this->validEventData(), JSON_THROW_ON_ERROR);

        $event = $this->decoder->decode($json);

        $this->assertSame('evt-12345', $event->eventId);
        $this->assertSame(NotificationSeverity::CRITICAL, $event->severity);
    }

    public function test_throws_on_malformed_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Malformed JSON envelope');

        $this->decoder->decode('{not valid json');
    }

    public function test_throws_on_missing_event_id(): void
    {
        $data = $this->validEventData();
        unset($data['event_id']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing or empty string property: 'event_id'");

        $this->decoder->decode($data);
    }

    public function test_throws_on_unsupported_event_type(): void
    {
        $data = $this->validEventData();
        $data['event_type'] = 'order.created';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported event_type: 'order.created'");

        $this->decoder->decode($data);
    }

    public function test_throws_on_unsupported_version(): void
    {
        $data = $this->validEventData();
        $data['event_version'] = 2;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported event_version: 2, expected 1');

        $this->decoder->decode($data);
    }

    public function test_throws_on_unsupported_producer(): void
    {
        $data = $this->validEventData();
        $data['producer'] = 'external';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported producer: 'external', expected 'analytics'");

        $this->decoder->decode($data);
    }

    public function test_throws_on_invalid_severity(): void
    {
        $data = $this->validEventData();
        $data['payload']['severity'] = 'unknown';

        $this->expectException(InvalidArgumentException::class);

        $this->decoder->decode($data);
    }

    public function test_accepts_null_rule_id(): void
    {
        $data = $this->validEventData();
        $data['payload']['rule_id'] = null;

        $event = $this->decoder->decode($data);

        $this->assertNull($event->ruleId);
    }

    public function test_decodes_optional_correlation_id_when_present(): void
    {
        $data = $this->validEventData();
        $data['correlation_id'] = 'corr-req-789';

        $event = $this->decoder->decode($data);

        $this->assertSame('corr-req-789', $event->correlationId);
    }

    /**
     * @return array<string, mixed>
     */
    private function validEventData(): array
    {
        return [
            'event_id' => 'evt-12345',
            'event_type' => 'alert.triggered',
            'event_version' => 1,
            'occurred_at' => '2026-09-23T10:00:00+00:00',
            'producer' => 'analytics',
            'workspace_id' => 'ws-1',
            'aggregate' => [
                'type' => 'alert',
                'id' => 'alt-1',
            ],
            'payload' => [
                'rule_id' => 'rule-1',
                'rule_name' => 'Low Stock',
                'severity' => 'critical',
                'metric' => 'quantity_available',
                'comparator' => 'lte',
                'current_value' => 5,
                'threshold_value' => 10,
                'analytical_context' => [
                    'target' => 'inventory',
                    'sku' => 'SKU-1',
                ],
            ],
        ];
    }
}
