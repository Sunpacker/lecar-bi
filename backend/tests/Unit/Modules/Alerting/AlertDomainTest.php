<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Alerting;

use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\AlertStatus;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Events\AlertTriggered;
use App\Modules\Alerting\Domain\Exceptions\InvalidAlertStateTransitionException;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Shared\Domain\DomainEventId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AlertDomainTest extends TestCase
{
    public function test_can_create_alert_in_open_status(): void
    {
        $context = new AlertContext(
            target: 'inventory',
            warehouseId: 'wh-1',
            warehouseName: 'Центральный склад',
            productId: 'prod-1',
            productName: 'Тормозные колодки',
            productSku: 'BRK-001',
            currentValue: 0.0,
            thresholdValue: 0.0,
        );

        $fingerprint = DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1');

        $alert = new Alert(
            id: new AlertId('alt-1'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Дефицит колодок',
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: $fingerprint,
            context: $context,
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );

        self::assertSame('alt-1', $alert->id()->value());
        self::assertSame(AlertStatus::OPEN, $alert->status());
        self::assertTrue($alert->isOpen());
        self::assertFalse($alert->isAcknowledged());
        self::assertFalse($alert->isResolved());
        self::assertSame('wh-1', $alert->context()->warehouseId());
        self::assertSame('BRK-001', $alert->context()->productSku());
    }

    public function test_can_acknowledge_open_alert(): void
    {
        $alert = $this->createTestAlert();

        $acknowledgedAt = new DateTimeImmutable('2026-09-23 10:15:00');
        $alert->acknowledge('user-1', $acknowledgedAt);

        self::assertSame(AlertStatus::ACKNOWLEDGED, $alert->status());
        self::assertTrue($alert->isAcknowledged());
        self::assertSame('user-1', $alert->acknowledgedBy());
        self::assertSame($acknowledgedAt, $alert->acknowledgedAt());
    }

    public function test_can_resolve_alert_with_note(): void
    {
        $alert = $this->createTestAlert();
        $alert->acknowledge('user-1', new DateTimeImmutable('2026-09-23 10:15:00'));

        $resolvedAt = new DateTimeImmutable('2026-09-23 11:00:00');
        $alert->resolve('user-1', 'Поставка оформлена', $resolvedAt);

        self::assertSame(AlertStatus::RESOLVED, $alert->status());
        self::assertTrue($alert->isResolved());
        self::assertSame('user-1', $alert->resolvedBy());
        self::assertSame($resolvedAt, $alert->resolvedAt());
        self::assertSame('Поставка оформлена', $alert->resolutionNote());
    }

    public function test_cannot_acknowledge_resolved_alert(): void
    {
        $alert = $this->createTestAlert();
        $alert->resolve('user-1', 'Решено', new DateTimeImmutable);

        $this->expectException(InvalidAlertStateTransitionException::class);
        $alert->acknowledge('user-2', new DateTimeImmutable);
    }

    public function test_retrigger_updates_current_value_and_timestamp(): void
    {
        $alert = $this->createTestAlert();

        $newTime = new DateTimeImmutable('2026-09-23 12:00:00');
        $alert->retrigger(-2.0, $newTime);

        self::assertSame(-2.0, $alert->context()->currentValue());
        self::assertSame($newTime, $alert->triggeredAt());
        self::assertSame(AlertStatus::OPEN, $alert->status());
    }

    public function test_dedup_fingerprint_is_deterministic(): void
    {
        $fp1 = DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1');
        $fp2 = DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1');
        $fp3 = DedupFingerprint::generate('ws-1', 'rule-1', 'prod-2', 'wh-1');

        self::assertSame($fp1->value(), $fp2->value());
        self::assertNotSame($fp1->value(), $fp3->value());
    }

    public function test_empty_alert_id_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AlertId('');
    }

    // --- Phase 13: Domain Event Tests ---

    public function test_trigger_factory_records_exactly_one_alert_triggered_event(): void
    {
        $now = new DateTimeImmutable('2026-09-23 10:00:00');
        $eventId = new DomainEventId('evt-test-001');

        $alert = Alert::trigger(
            id: new AlertId('alt-1'),
            eventId: $eventId,
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Дефицит колодок',
            severity: AlertSeverity::CRITICAL,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад',
                productId: 'prod-1',
                productName: 'Тормозные колодки',
                productSku: 'BRK-001',
                currentValue: 0.0,
                thresholdValue: 0.0,
            ),
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            now: $now,
        );

        $events = $alert->peekDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(AlertTriggered::class, $events[0]);

        $event = $events[0];
        self::assertSame('evt-test-001', $event->eventId()->value());
        self::assertSame('alt-1', $event->alertId()->value());
        self::assertSame('ws-1', $event->workspaceId());
        self::assertSame(AlertSeverity::CRITICAL, $event->severity());
        self::assertSame($now, $event->occurredAt());
        self::assertSame(RuleMetric::QUANTITY_AVAILABLE, $event->metric());
        self::assertSame(RuleComparator::LESS_THAN_OR_EQUAL, $event->comparator());
    }

    public function test_trigger_factory_creates_open_alert(): void
    {
        $alert = Alert::trigger(
            id: new AlertId('alt-1'),
            eventId: new DomainEventId('evt-test-002'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Дефицит',
            severity: AlertSeverity::WARNING,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1'),
            context: new AlertContext(target: 'inventory', currentValue: 5.0, thresholdValue: 10.0),
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            now: new DateTimeImmutable,
        );

        self::assertSame(AlertStatus::OPEN, $alert->status());
    }

    public function test_retrigger_does_not_record_domain_event(): void
    {
        $alert = Alert::trigger(
            id: new AlertId('alt-1'),
            eventId: new DomainEventId('evt-test-003'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Дефицит',
            severity: AlertSeverity::CRITICAL,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1'),
            context: new AlertContext(target: 'inventory', currentValue: 5.0, thresholdValue: 10.0),
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            now: new DateTimeImmutable,
        );

        // Release events from trigger
        $alert->releaseDomainEvents();

        // Retrigger should NOT record new domain event
        $alert->retrigger(3.0, new DateTimeImmutable);

        self::assertCount(0, $alert->peekDomainEvents());
    }

    public function test_rehydration_via_constructor_does_not_record_domain_event(): void
    {
        // Simulates loading an existing alert from the database (rehydration)
        $alert = $this->createTestAlert();

        // No events should be recorded on rehydration
        self::assertCount(0, $alert->peekDomainEvents());
    }

    public function test_acknowledge_does_not_record_domain_event(): void
    {
        $alert = $this->createTestAlert();
        $alert->releaseDomainEvents(); // clear any existing events

        $alert->acknowledge('user-1', new DateTimeImmutable);

        self::assertCount(0, $alert->peekDomainEvents());
    }

    public function test_resolve_does_not_record_domain_event(): void
    {
        $alert = $this->createTestAlert();
        $alert->releaseDomainEvents();

        $alert->resolve('user-1', 'Fixed', new DateTimeImmutable);

        self::assertCount(0, $alert->peekDomainEvents());
    }

    public function test_release_domain_events_clears_the_list(): void
    {
        $alert = Alert::trigger(
            id: new AlertId('alt-1'),
            eventId: new DomainEventId('evt-test-004'),
            workspaceId: 'ws-1',
            ruleId: null,
            ruleName: 'Дефицит',
            severity: AlertSeverity::INFO,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1'),
            context: new AlertContext(target: 'inventory'),
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            now: new DateTimeImmutable,
        );

        $released = $alert->releaseDomainEvents();
        self::assertCount(1, $released);

        // After release, no events remain
        self::assertCount(0, $alert->peekDomainEvents());
        self::assertCount(0, $alert->releaseDomainEvents());
    }

    public function test_domain_event_id_is_unique(): void
    {
        $id1 = DomainEventId::generate();
        $id2 = DomainEventId::generate();

        self::assertNotSame($id1->value(), $id2->value());
        self::assertStringStartsWith('evt-', $id1->value());
    }

    private function createTestAlert(): Alert
    {
        return new Alert(
            id: new AlertId('alt-1'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Дефицит колодок',
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад',
                productId: 'prod-1',
                productName: 'Тормозные колодки',
                productSku: 'BRK-001',
                currentValue: 0.0,
                thresholdValue: 0.0,
            ),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );
    }
}
