<?php

declare(strict_types=1);

namespace Database\Seeders;

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
use DateTimeImmutable;
use Illuminate\Database\Seeder;

final class AlertingSeeder extends Seeder
{
    public function run(
        AlertRuleRepositoryInterface $ruleRepository,
        AlertRepositoryInterface $alertRepository,
    ): void {
        $now = new DateTimeImmutable;
        $oneHourAgo = $now->modify('-1 hour');
        $twoHoursAgo = $now->modify('-2 hours');
        $oneDayAgo = $now->modify('-1 day');

        // ==================== WORKSPACE 1 ====================
        // Rule 1: Критический дефицит (DOS < 7)
        $rule1 = new AlertRule(
            id: new AlertRuleId('b0000001-0000-4000-8000-000000000001'),
            workspaceId: 'ws-1',
            name: 'Критический дефицит (DOS < 7 дней)',
            description: 'Остаток товара покрывает менее 7 дней продаж. Требуется срочный заказ поставщику.',
            ruleType: RuleType::CRITICAL_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(
                metric: RuleMetric::DAYS_OF_STOCK,
                comparator: RuleComparator::LESS_THAN,
                thresholdValue: 7.0,
            ),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: $oneDayAgo,
            updatedAt: $oneDayAgo,
        );
        $ruleRepository->save($rule1);

        // Rule 2: Аут-оф-сток (нулевой остаток)
        $rule2 = new AlertRule(
            id: new AlertRuleId('b0000001-0000-4000-8000-000000000002'),
            workspaceId: 'ws-1',
            name: 'Аут-оф-сток (нулевой остаток)',
            description: 'Товар полностью закончился на складе при наличии спроса.',
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(
                metric: RuleMetric::QUANTITY_AVAILABLE,
                comparator: RuleComparator::LESS_THAN_OR_EQUAL,
                thresholdValue: 0.0,
            ),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: $oneDayAgo,
            updatedAt: $oneDayAgo,
        );
        $ruleRepository->save($rule2);

        // Rule 3: Залежалый товар (DOS > 60 дней)
        $rule3 = new AlertRule(
            id: new AlertRuleId('b0000001-0000-4000-8000-000000000003'),
            workspaceId: 'ws-1',
            name: 'Залежалый товар / Overstock (DOS > 60 дней)',
            description: 'Избыточный объем запасов замораживает оборотный капитал.',
            ruleType: RuleType::OVERSTOCK,
            severity: AlertSeverity::WARNING,
            condition: new RuleCondition(
                metric: RuleMetric::DAYS_OF_STOCK,
                comparator: RuleComparator::GREATER_THAN,
                thresholdValue: 60.0,
            ),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: $oneDayAgo,
            updatedAt: $oneDayAgo,
        );
        $ruleRepository->save($rule3);

        // Rule 4: Точка повторного заказа (Остаток <= 10 шт)
        $rule4 = new AlertRule(
            id: new AlertRuleId('b0000001-0000-4000-8000-000000000004'),
            workspaceId: 'ws-1',
            name: 'Точка повторного заказа (Остаток <= 10 шт)',
            description: 'Регулярное пополнение запасов при приближении к страховому остатку.',
            ruleType: RuleType::REORDER_POINT,
            severity: AlertSeverity::INFO,
            condition: new RuleCondition(
                metric: RuleMetric::QUANTITY_AVAILABLE,
                comparator: RuleComparator::LESS_THAN_OR_EQUAL,
                thresholdValue: 10.0,
            ),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: $oneDayAgo,
            updatedAt: $oneDayAgo,
        );
        $ruleRepository->save($rule4);

        // Демо алерты для ws-1:
        // Alert 1: OPEN, CRITICAL
        $alert1 = new Alert(
            id: new AlertId('c0000001-0000-4000-8000-000000000001'),
            workspaceId: 'ws-1',
            ruleId: $rule2->id(),
            ruleName: $rule2->name(),
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', $rule2->id()->value(), 'prod-filt-01', 'wh-1'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад (Москва)',
                productId: 'prod-filt-01',
                productName: 'Масляный фильтр BOSCH P3314',
                productSku: 'FILT-001',
                currentValue: 0.0,
                thresholdValue: 0.0,
            ),
            triggeredAt: $oneHourAgo,
            createdAt: $oneHourAgo,
            updatedAt: $oneHourAgo,
        );
        $alertRepository->save($alert1);

        // Alert 2: ACKNOWLEDGED, CRITICAL
        $alert2 = new Alert(
            id: new AlertId('c0000001-0000-4000-8000-000000000002'),
            workspaceId: 'ws-1',
            ruleId: $rule1->id(),
            ruleName: $rule1->name(),
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::ACKNOWLEDGED,
            dedupFingerprint: DedupFingerprint::generate('ws-1', $rule1->id()->value(), 'prod-brk-02', 'wh-1'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад (Москва)',
                productId: 'prod-brk-02',
                productName: 'Колодки тормозные передние Brembo P85020',
                productSku: 'BRK-002',
                currentValue: 3.5,
                thresholdValue: 7.0,
            ),
            triggeredAt: $twoHoursAgo,
            createdAt: $twoHoursAgo,
            updatedAt: $oneHourAgo,
            acknowledgedAt: $oneHourAgo,
            acknowledgedBy: 'user-1',
        );
        $alertRepository->save($alert2);

        // Alert 3: OPEN, WARNING
        $alert3 = new Alert(
            id: new AlertId('c0000001-0000-4000-8000-000000000003'),
            workspaceId: 'ws-1',
            ruleId: $rule3->id(),
            ruleName: $rule3->name(),
            severity: AlertSeverity::WARNING,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', $rule3->id()->value(), 'prod-shk-03', 'wh-2'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-2',
                warehouseName: 'Северный хаб (СПб)',
                productId: 'prod-shk-03',
                productName: 'Амортизатор передний правый Excel-G KYB 333338',
                productSku: 'SHK-003',
                currentValue: 92.0,
                thresholdValue: 60.0,
            ),
            triggeredAt: $twoHoursAgo,
            createdAt: $twoHoursAgo,
            updatedAt: $twoHoursAgo,
        );
        $alertRepository->save($alert3);

        // Alert 4: RESOLVED, INFO
        $alert4 = new Alert(
            id: new AlertId('c0000001-0000-4000-8000-000000000004'),
            workspaceId: 'ws-1',
            ruleId: $rule4->id(),
            ruleName: $rule4->name(),
            severity: AlertSeverity::INFO,
            status: AlertStatus::RESOLVED,
            dedupFingerprint: DedupFingerprint::generate('ws-1', $rule4->id()->value(), 'prod-spk-04', 'wh-1'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад (Москва)',
                productId: 'prod-spk-04',
                productName: 'Свеча зажигания NGK BKR6E-11',
                productSku: 'SPK-004',
                currentValue: 8.0,
                thresholdValue: 10.0,
            ),
            triggeredAt: $oneDayAgo,
            createdAt: $oneDayAgo,
            updatedAt: $twoHoursAgo,
            acknowledgedAt: $oneDayAgo,
            acknowledgedBy: 'user-1',
            resolvedAt: $twoHoursAgo,
            resolvedBy: 'user-1',
            resolutionNote: 'Поставка от производителя принята на склад, остаток увеличен до 120 шт.',
        );
        $alertRepository->save($alert4);

        // ==================== WORKSPACE 2 ====================
        $ws2Rule = new AlertRule(
            id: new AlertRuleId('b0000002-0000-4000-8000-000000000001'),
            workspaceId: 'ws-2',
            name: 'Критический дефицит на оптовом складе',
            description: 'Контроль остатков для оптовых отгрузок',
            ruleType: RuleType::CRITICAL_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(
                metric: RuleMetric::DAYS_OF_STOCK,
                comparator: RuleComparator::LESS_THAN,
                thresholdValue: 14.0,
            ),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: $oneDayAgo,
            updatedAt: $oneDayAgo,
        );
        $ruleRepository->save($ws2Rule);

        $ws2Alert = new Alert(
            id: new AlertId('c0000002-0000-4000-8000-000000000001'),
            workspaceId: 'ws-2',
            ruleId: $ws2Rule->id(),
            ruleName: $ws2Rule->name(),
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-2', $ws2Rule->id()->value(), 'prod-ws2-01', 'wh-opt'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-opt',
                warehouseName: 'Главный распределительный центр',
                productId: 'prod-ws2-01',
                productName: 'Моторное масло 5W-40 200л (бочка)',
                productSku: 'OIL-200L',
                currentValue: 5.0,
                thresholdValue: 14.0,
            ),
            triggeredAt: $oneHourAgo,
            createdAt: $oneHourAgo,
            updatedAt: $oneHourAgo,
        );
        $alertRepository->save($ws2Alert);
    }
}
