<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Alerting;

use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\AlertStatus;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleCondition;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleScope;
use App\Modules\Alerting\Domain\RuleType;
use App\Modules\Alerting\Infrastructure\Repositories\InMemoryAlertRepository;
use App\Modules\Alerting\Infrastructure\Repositories\InMemoryAlertRuleRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AlertingRepositoryTest extends TestCase
{
    private AlertRuleRepositoryInterface $ruleRepo;

    private AlertRepositoryInterface $alertRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleRepo = new InMemoryAlertRuleRepository;
        $this->alertRepo = new InMemoryAlertRepository;
    }

    public function test_alert_rule_crud(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('r-1'),
            workspaceId: 'ws-1',
            name: 'Дефицит тормозов',
            description: 'Проверка нулевого остатка',
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN_OR_EQUAL, 0.0),
            scope: new RuleScope(warehouseId: 'wh-1'),
            isEnabled: true,
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );

        $this->ruleRepo->save($rule);

        $found = $this->ruleRepo->findById('ws-1', new AlertRuleId('r-1'));
        self::assertNotNull($found);
        self::assertSame('Дефицит тормозов', $found->name());
        self::assertTrue($found->isEnabled());

        $ws2Found = $this->ruleRepo->findById('ws-2', new AlertRuleId('r-1'));
        self::assertNull($ws2Found, 'Workspace isolation must be enforced');

        $enabled = $this->ruleRepo->findEnabledByWorkspaceId('ws-1');
        self::assertCount(1, $enabled);

        $this->ruleRepo->delete('ws-1', new AlertRuleId('r-1'));
        self::assertNull($this->ruleRepo->findById('ws-1', new AlertRuleId('r-1')));
    }

    public function test_alert_storage_and_active_dedup_query(): void
    {
        $fingerprint = DedupFingerprint::generate('ws-1', 'r-1', 'p-1', 'wh-1');

        $alert = new Alert(
            id: new AlertId('a-1'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('r-1'),
            ruleName: 'Дефицит',
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: $fingerprint,
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Склад 1',
                productId: 'p-1',
                productName: 'Колодки',
                productSku: 'BRK-100',
                currentValue: 0.0,
                thresholdValue: 0.0,
            ),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );

        $this->alertRepo->save($alert);

        $found = $this->alertRepo->findById('ws-1', new AlertId('a-1'));
        self::assertNotNull($found);
        self::assertSame('BRK-100', $found->context()->productSku());

        // Find active by fingerprint
        $activeAlert = $this->alertRepo->findActiveByFingerprint('ws-1', $fingerprint);
        self::assertNotNull($activeAlert);
        self::assertSame('a-1', $activeAlert->id()->value());

        // Resolve alert
        $activeAlert->resolve('user-1', 'Пополнено');
        $this->alertRepo->save($activeAlert);

        // After resolve, active alert by fingerprint should be null
        $noActiveAlert = $this->alertRepo->findActiveByFingerprint('ws-1', $fingerprint);
        self::assertNull($noActiveAlert);

        // Summary counts
        $summary = $this->alertRepo->getSummaryCounts('ws-1');
        self::assertSame(0, $summary['total_active']);
    }

    public function test_alert_listing_and_filtering(): void
    {
        $alertOpen = new Alert(
            id: new AlertId('a-1'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('r-1'),
            ruleName: 'Дефицит',
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'r-1', 'p-1', 'wh-1'),
            context: new AlertContext('inventory', 'wh-1', 'Склад 1', 'p-1', 'Колодки', 'BRK-1', 0.0, 0.0),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );

        $alertAck = new Alert(
            id: new AlertId('a-2'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('r-2'),
            ruleName: 'Затоваривание',
            severity: AlertSeverity::WARNING,
            status: AlertStatus::ACKNOWLEDGED,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'r-2', 'p-2', 'wh-2'),
            context: new AlertContext('inventory', 'wh-2', 'Склад 2', 'p-2', 'Фильтр', 'FLT-1', 80.0, 60.0),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:30:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:30:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:30:00'),
            acknowledgedAt: new DateTimeImmutable('2026-09-23 10:35:00'),
            acknowledgedBy: 'user-1',
        );

        $this->alertRepo->save($alertOpen);
        $this->alertRepo->save($alertAck);

        $activeAlerts = $this->alertRepo->listAlerts('ws-1', status: 'active');
        self::assertCount(2, $activeAlerts);
        // Ordered desc by triggeredAt: a-2 first, then a-1
        self::assertSame('a-2', $activeAlerts[0]->id()->value());
        self::assertSame('a-1', $activeAlerts[1]->id()->value());

        $critAlerts = $this->alertRepo->listAlerts('ws-1', severity: AlertSeverity::CRITICAL);
        self::assertCount(1, $critAlerts);
        self::assertSame('a-1', $critAlerts[0]->id()->value());

        $wh2Alerts = $this->alertRepo->listAlerts('ws-1', warehouseId: 'wh-2');
        self::assertCount(1, $wh2Alerts);
        self::assertSame('a-2', $wh2Alerts[0]->id()->value());

        $summary = $this->alertRepo->getSummaryCounts('ws-1');
        self::assertSame(2, $summary['total_active']);
        self::assertSame(1, $summary['critical_count']);
        self::assertSame(1, $summary['warning_count']);
        self::assertSame(1, $summary['acknowledged_count']);
    }
}
