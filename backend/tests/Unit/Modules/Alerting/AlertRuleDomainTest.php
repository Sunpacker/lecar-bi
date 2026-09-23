<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Alerting;

use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleCondition;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleScope;
use App\Modules\Alerting\Domain\RuleType;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AlertRuleDomainTest extends TestCase
{
    public function test_can_create_and_toggle_alert_rule(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('rule-1'),
            workspaceId: 'ws-1',
            name: 'Критический остаток',
            description: 'Остаток ниже 7 дней',
            ruleType: RuleType::CRITICAL_STOCK,
            severity: AlertSeverity::WARNING,
            condition: new RuleCondition(
                metric: RuleMetric::DAYS_OF_STOCK,
                comparator: RuleComparator::LESS_THAN_OR_EQUAL,
                thresholdValue: 7.0,
            ),
            scope: new RuleScope(warehouseId: 'wh-1'),
            isEnabled: true,
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );

        self::assertSame('rule-1', $rule->id()->value());
        self::assertSame('ws-1', $rule->workspaceId());
        self::assertSame('Критический остаток', $rule->name());
        self::assertTrue($rule->isEnabled());
        self::assertSame('wh-1', $rule->scope()->warehouseId());
        self::assertSame(7.0, $rule->condition()->thresholdValue());

        $rule->disable();
        self::assertFalse($rule->isEnabled());

        $rule->enable();
        self::assertTrue($rule->isEnabled());
    }

    public function test_can_update_alert_rule(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('rule-1'),
            workspaceId: 'ws-1',
            name: 'Критический остаток',
            description: null,
            ruleType: RuleType::CRITICAL_STOCK,
            severity: AlertSeverity::WARNING,
            condition: new RuleCondition(
                metric: RuleMetric::DAYS_OF_STOCK,
                comparator: RuleComparator::LESS_THAN_OR_EQUAL,
                thresholdValue: 7.0,
            ),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );

        $rule->update(
            name: 'Обновленное правило',
            description: 'Новое описание',
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(
                metric: RuleMetric::QUANTITY_AVAILABLE,
                comparator: RuleComparator::LESS_THAN_OR_EQUAL,
                thresholdValue: 0.0,
            ),
            scope: new RuleScope(warehouseId: 'wh-2'),
        );

        self::assertSame('Обновленное правило', $rule->name());
        self::assertSame('Новое описание', $rule->description());
        self::assertSame(AlertSeverity::CRITICAL, $rule->severity());
        self::assertSame('wh-2', $rule->scope()->warehouseId());
        self::assertSame(RuleMetric::QUANTITY_AVAILABLE, $rule->condition()->metric());
    }

    public function test_empty_id_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AlertRuleId('');
    }

    public function test_empty_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AlertRule(
            id: new AlertRuleId('rule-1'),
            workspaceId: 'ws-1',
            name: '   ',
            description: null,
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN_OR_EQUAL, 0.0),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
    }
}
