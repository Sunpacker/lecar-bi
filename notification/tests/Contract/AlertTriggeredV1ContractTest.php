<?php

declare(strict_types=1);

namespace NotificationService\Tests\Contract;

use NotificationService\Notification\Application\AlertTriggeredV1Decoder;
use NotificationService\Notification\Domain\NotificationSeverity;
use PHPUnit\Framework\TestCase;

final class AlertTriggeredV1ContractTest extends TestCase
{
    public function test_decoder_accepts_canonical_contract_example(): void
    {
        $fixturePath = dirname(__DIR__, 3).'/contracts/events/alert-triggered.v1.example.json';

        $this->assertFileExists($fixturePath, "Canonical fixture not found at {$fixturePath}");

        $rawJson = (string) file_get_contents($fixturePath);
        $decoder = new AlertTriggeredV1Decoder;

        $event = $decoder->decode($rawJson);

        $this->assertSame('evt-0123456789abcdef0123456789abcdef', $event->eventId);
        $this->assertSame('alert.triggered', $event->eventType);
        $this->assertSame(1, $event->eventVersion);
        $this->assertSame('analytics', $event->producer);
        $this->assertSame('ws-demo-001', $event->workspaceId);
        $this->assertSame('alt-0a1b2c3d4e5f', $event->alertId);
        $this->assertSame('rule-reorder-kolodki', $event->ruleId);
        $this->assertSame('Reorder Point — Тормозные колодки', $event->ruleName);
        $this->assertSame(NotificationSeverity::CRITICAL, $event->severity);
        $this->assertSame('quantity_available', $event->metric);
        $this->assertSame('lte', $event->comparator);
        $this->assertSame(5.0, $event->currentValue);
        $this->assertSame(20.0, $event->thresholdValue);
        $this->assertSame('inventory', $event->analyticalContext['target']);
        $this->assertSame('prod-kolodki-001', $event->analyticalContext['product_id']);
    }
}
