<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesCommand;
use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesHandler;
use App\Modules\Alerting\Application\Contracts\InventoryAlertSourceInterface;
use App\Modules\Alerting\Application\Dtos\InventoryCandidateDto;
use App\Modules\Alerting\Application\Mappers\AlertTriggeredIntegrationMapper;
use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleCondition;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleScope;
use App\Modules\Alerting\Domain\RuleType;
use App\Modules\Alerting\Infrastructure\Adapters\InMemoryInventoryAlertSource;
use App\Shared\Infrastructure\Outbox\InMemoryOutboxRepository;
use App\Shared\Infrastructure\Persistence\NoOpTransactionManager;
use DateTimeImmutable;
use Tests\TestCase;

final class AlertEvaluationEngineTest extends TestCase
{
    private AlertRuleRepositoryInterface $ruleRepo;

    private AlertRepositoryInterface $alertRepo;

    private InMemoryInventoryAlertSource $inventorySource;

    private InMemoryOutboxRepository $outboxRepo;

    private EvaluateAlertRulesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruleRepo = $this->app->make(AlertRuleRepositoryInterface::class);
        $this->alertRepo = $this->app->make(AlertRepositoryInterface::class);

        $this->inventorySource = new InMemoryInventoryAlertSource;
        $this->app->instance(InventoryAlertSourceInterface::class, $this->inventorySource);

        $this->outboxRepo = new InMemoryOutboxRepository;

        $this->handler = new EvaluateAlertRulesHandler(
            $this->ruleRepo,
            $this->alertRepo,
            $this->inventorySource,
            $this->outboxRepo,
            new NoOpTransactionManager,
            new AlertTriggeredIntegrationMapper,
        );
    }

    public function test_evaluation_creates_alert_for_stockout(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('r-out-of-stock'),
            workspaceId: 'ws-1',
            name: 'Дефицит',
            description: 'Товар закончился',
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN_OR_EQUAL, 0.0),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
        $this->ruleRepo->save($rule);

        $candidates = [
            new InventoryCandidateDto(
                productId: 'p-1',
                productName: 'Колодки передние',
                productSku: 'PAD-001',
                categoryId: 'cat-1',
                categoryName: 'Тормоза',
                warehouseId: 'wh-1',
                warehouseName: 'Центр',
                quantityOnHand: 0,
                quantityReserved: 0,
                quantityAvailable: 0,
                safetyStock: 10,
                reorderPoint: 20,
                unitCost: 1500.0,
                inventoryValue: 0.0,
                dailyVelocity: 2.5,
                daysOfStock: 0.0,
            ),
            new InventoryCandidateDto(
                productId: 'p-2',
                productName: 'Масло моторное',
                productSku: 'OIL-005',
                categoryId: 'cat-2',
                categoryName: 'Масла',
                warehouseId: 'wh-1',
                warehouseName: 'Центр',
                quantityOnHand: 50,
                quantityReserved: 5,
                quantityAvailable: 45,
                safetyStock: 10,
                reorderPoint: 20,
                unitCost: 800.0,
                inventoryValue: 40000.0,
                dailyVelocity: 1.0,
                daysOfStock: 45.0,
            ),
        ];

        $this->inventorySource->setCandidates('ws-1', $candidates);

        $result = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));

        self::assertSame(1, $result->rulesEvaluated);
        self::assertSame(1, $result->alertsTriggered);
        self::assertSame(1, $result->alertsCreated);
        self::assertSame(0, $result->alertsUpdated);

        $alerts = $this->alertRepo->listAlerts('ws-1');
        self::assertCount(1, $alerts);
        self::assertSame('PAD-001', $alerts[0]->context()->productSku());
        self::assertSame(AlertSeverity::CRITICAL, $alerts[0]->severity());
        self::assertTrue($alerts[0]->isOpen());
    }

    public function test_evaluation_is_strictly_idempotent_and_does_not_create_duplicates(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('r-out-of-stock'),
            workspaceId: 'ws-1',
            name: 'Дефицит',
            description: null,
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN_OR_EQUAL, 0.0),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
        $this->ruleRepo->save($rule);

        $this->inventorySource->setCandidates('ws-1', [
            new InventoryCandidateDto(
                productId: 'p-1',
                productName: 'Колодки',
                productSku: 'PAD-001',
                categoryId: null,
                categoryName: null,
                warehouseId: 'wh-1',
                warehouseName: 'Центр',
                quantityOnHand: 0,
                quantityReserved: 0,
                quantityAvailable: 0,
                safetyStock: 10,
                reorderPoint: 20,
                unitCost: 1500.0,
                inventoryValue: 0.0,
                dailyVelocity: 2.0,
                daysOfStock: 0.0,
            ),
        ]);

        // First run: creates alert
        $result1 = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));
        self::assertSame(1, $result1->alertsCreated);
        self::assertSame(0, $result1->alertsUpdated);

        // Verify exactly 1 alert in repo
        self::assertSame(1, $this->alertRepo->countAlerts('ws-1'));

        // Second run: MUST NOT CREATE DUPLICATE!
        $result2 = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));
        self::assertSame(0, $result2->alertsCreated, 'Evaluation must not create duplicates');
        self::assertSame(1, $result2->alertsUpdated, 'Existing alert should be updated');

        // Third run: STILL exactly 1 alert in repo!
        $result3 = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));
        self::assertSame(0, $result3->alertsCreated);
        self::assertSame(1, $result3->alertsUpdated);

        self::assertSame(1, $this->alertRepo->countAlerts('ws-1'));
    }

    public function test_disabled_rule_is_not_evaluated(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('r-disabled'),
            workspaceId: 'ws-1',
            name: 'Отключенное правило',
            description: null,
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::INFO,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN_OR_EQUAL, 0.0),
            scope: new RuleScope,
            isEnabled: false,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
        $this->ruleRepo->save($rule);

        $this->inventorySource->setCandidates('ws-1', [
            new InventoryCandidateDto(
                productId: 'p-1',
                productName: 'Колодки',
                productSku: 'PAD-001',
                categoryId: null,
                categoryName: null,
                warehouseId: 'wh-1',
                warehouseName: 'Центр',
                quantityOnHand: 0,
                quantityReserved: 0,
                quantityAvailable: 0,
                safetyStock: 10,
                reorderPoint: 20,
                unitCost: 1500.0,
                inventoryValue: 0.0,
                dailyVelocity: 2.0,
                daysOfStock: 0.0,
            ),
        ]);

        $result = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));
        self::assertSame(0, $result->rulesEvaluated);
        self::assertSame(0, $result->alertsCreated);
    }

    public function test_warehouse_scope_filters_properly(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('r-wh2-only'),
            workspaceId: 'ws-1',
            name: 'Дефицит склада 2',
            description: null,
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::WARNING,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN_OR_EQUAL, 0.0),
            scope: new RuleScope(warehouseId: 'wh-2'),
            isEnabled: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
        $this->ruleRepo->save($rule);

        $this->inventorySource->setCandidates('ws-1', [
            new InventoryCandidateDto(
                productId: 'p-1',
                productName: 'Колодки',
                productSku: 'PAD-001',
                categoryId: null,
                categoryName: null,
                warehouseId: 'wh-1', // Doesn't match scope
                warehouseName: 'Склад 1',
                quantityOnHand: 0,
                quantityReserved: 0,
                quantityAvailable: 0,
                safetyStock: 10,
                reorderPoint: 20,
                unitCost: 1500.0,
                inventoryValue: 0.0,
                dailyVelocity: 2.0,
                daysOfStock: 0.0,
            ),
            new InventoryCandidateDto(
                productId: 'p-1',
                productName: 'Колодки',
                productSku: 'PAD-001',
                categoryId: null,
                categoryName: null,
                warehouseId: 'wh-2', // Matches scope
                warehouseName: 'Склад 2',
                quantityOnHand: 0,
                quantityReserved: 0,
                quantityAvailable: 0,
                safetyStock: 10,
                reorderPoint: 20,
                unitCost: 1500.0,
                inventoryValue: 0.0,
                dailyVelocity: 2.0,
                daysOfStock: 0.0,
            ),
        ]);

        $result = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));
        self::assertSame(1, $result->alertsCreated);

        $alerts = $this->alertRepo->listAlerts('ws-1');
        self::assertCount(1, $alerts);
        self::assertSame('wh-2', $alerts[0]->context()->warehouseId());
    }

    public function test_new_alert_registers_outbox_message(): void
    {
        $rule = new AlertRule(
            id: new AlertRuleId('r-outbox-test'),
            workspaceId: 'ws-1',
            name: 'Дефицит',
            description: null,
            ruleType: RuleType::OUT_OF_STOCK,
            severity: AlertSeverity::CRITICAL,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN_OR_EQUAL, 0.0),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
        $this->ruleRepo->save($rule);

        $this->inventorySource->setCandidates('ws-1', [
            new InventoryCandidateDto(
                productId: 'p-1',
                productName: 'Колодки',
                productSku: 'PAD-001',
                categoryId: null,
                categoryName: null,
                warehouseId: 'wh-1',
                warehouseName: 'Центр',
                quantityOnHand: 0,
                quantityReserved: 0,
                quantityAvailable: 0,
                safetyStock: 10,
                reorderPoint: 20,
                unitCost: 1500.0,
                inventoryValue: 0.0,
                dailyVelocity: 2.0,
                daysOfStock: 0.0,
            ),
        ]);

        // First run: creates alert + outbox message
        $result = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));
        self::assertSame(1, $result->alertsCreated);

        $pendingMessages = $this->outboxRepo->byStatus('pending');
        self::assertCount(1, $pendingMessages, 'New alert must register exactly one outbox message');
        self::assertSame('alert.triggered', $pendingMessages[0]['event_type']);
        self::assertSame('ws-1', $pendingMessages[0]['workspace_id']);
        self::assertSame('alert', $pendingMessages[0]['aggregate_type']);

        // Second run: retrigger — MUST NOT create additional outbox message
        $result2 = $this->handler->handle(new EvaluateAlertRulesCommand('ws-1'));
        self::assertSame(0, $result2->alertsCreated);
        self::assertSame(1, $result2->alertsUpdated);

        // Still only the one outbox message from the first run
        self::assertCount(1, $this->outboxRepo->byStatus('pending'));
    }
}
