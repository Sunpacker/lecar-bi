<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Alerting;

use App\Modules\Alerting\Application\Commands\AcknowledgeAlertCommand;
use App\Modules\Alerting\Application\Commands\AcknowledgeAlertHandler;
use App\Modules\Alerting\Application\Commands\CreateAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\CreateAlertRuleHandler;
use App\Modules\Alerting\Application\Commands\DeleteAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\DeleteAlertRuleHandler;
use App\Modules\Alerting\Application\Commands\ResolveAlertCommand;
use App\Modules\Alerting\Application\Commands\ResolveAlertHandler;
use App\Modules\Alerting\Application\Commands\ToggleAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\ToggleAlertRuleHandler;
use App\Modules\Alerting\Application\Commands\UpdateAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\UpdateAlertRuleHandler;
use App\Modules\Alerting\Application\Queries\GetAlertByIdHandler;
use App\Modules\Alerting\Application\Queries\GetAlertByIdQuery;
use App\Modules\Alerting\Application\Queries\GetAlertRuleByIdHandler;
use App\Modules\Alerting\Application\Queries\GetAlertRuleByIdQuery;
use App\Modules\Alerting\Application\Queries\GetAlertRulesHandler;
use App\Modules\Alerting\Application\Queries\GetAlertRulesQuery;
use App\Modules\Alerting\Application\Queries\GetAlertsHandler;
use App\Modules\Alerting\Application\Queries\GetAlertsQuery;
use App\Modules\Alerting\Application\Queries\GetAlertSummaryHandler;
use App\Modules\Alerting\Application\Queries\GetAlertSummaryQuery;
use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\AlertStatus;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Alerting\Domain\Exceptions\AlertRuleNotFoundException;
use App\Modules\Alerting\Infrastructure\Repositories\InMemoryAlertRepository;
use App\Modules\Alerting\Infrastructure\Repositories\InMemoryAlertRuleRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AlertCommandsAndQueriesTest extends TestCase
{
    private InMemoryAlertRuleRepository $ruleRepo;

    private InMemoryAlertRepository $alertRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleRepo = new InMemoryAlertRuleRepository;
        $this->alertRepo = new InMemoryAlertRepository;
    }

    public function test_create_and_manage_alert_rules(): void
    {
        $createHandler = new CreateAlertRuleHandler($this->ruleRepo);
        $getRulesHandler = new GetAlertRulesHandler($this->ruleRepo);
        $getRuleByIdHandler = new GetAlertRuleByIdHandler($this->ruleRepo);
        $updateHandler = new UpdateAlertRuleHandler($this->ruleRepo);
        $toggleHandler = new ToggleAlertRuleHandler($this->ruleRepo);
        $deleteHandler = new DeleteAlertRuleHandler($this->ruleRepo);

        // 1. Create
        $dto = $createHandler->handle(new CreateAlertRuleCommand(
            workspaceId: 'ws-1',
            name: 'Дефицит свечей',
            description: 'Проверка остатка свечей',
            ruleType: 'out_of_stock',
            severity: 'critical',
            metric: 'quantity_available',
            comparator: 'lte',
            thresholdValue: 0.0,
            warehouseId: 'wh-1',
            id: 'rule-test-1',
        ));

        self::assertSame('rule-test-1', $dto->id);
        self::assertSame('Дефицит свечей', $dto->name);
        self::assertTrue($dto->isEnabled);

        // 2. Query list
        $list = $getRulesHandler->handle(new GetAlertRulesQuery('ws-1'));
        self::assertCount(1, $list);

        // 3. Query by ID
        $single = $getRuleByIdHandler->handle(new GetAlertRuleByIdQuery('ws-1', 'rule-test-1'));
        self::assertSame('Дефицит свечей', $single->name);

        // 4. Update
        $updated = $updateHandler->handle(new UpdateAlertRuleCommand(
            workspaceId: 'ws-1',
            id: 'rule-test-1',
            name: 'Обновленное имя',
            description: 'Новое описание',
            severity: 'warning',
            metric: 'quantity_available',
            comparator: 'lte',
            thresholdValue: 5.0,
        ));
        self::assertSame('Обновленное имя', $updated->name);
        self::assertSame('warning', $updated->severity);

        // 5. Toggle
        $toggled = $toggleHandler->handle(new ToggleAlertRuleCommand('ws-1', 'rule-test-1'));
        self::assertFalse($toggled->isEnabled);

        // 6. Delete
        $deleteHandler->handle(new DeleteAlertRuleCommand('ws-1', 'rule-test-1'));

        $this->expectException(AlertRuleNotFoundException::class);
        $getRuleByIdHandler->handle(new GetAlertRuleByIdQuery('ws-1', 'rule-test-1'));
    }

    public function test_acknowledge_and_resolve_alert_lifecycle(): void
    {
        $alert = new Alert(
            id: new AlertId('alt-1'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Тестовое правило',
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'p-1', 'wh-1'),
            context: new AlertContext('inventory', 'wh-1', 'Склад', 'p-1', 'Товар', 'SKU-1', 0.0, 0.0),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );
        $this->alertRepo->save($alert);

        $ackHandler = new AcknowledgeAlertHandler($this->alertRepo);
        $resolveHandler = new ResolveAlertHandler($this->alertRepo);
        $getByIdHandler = new GetAlertByIdHandler($this->alertRepo);
        $getAlertsHandler = new GetAlertsHandler($this->alertRepo);
        $summaryHandler = new GetAlertSummaryHandler($this->alertRepo);

        // Check summary before ack
        $summary1 = $summaryHandler->handle(new GetAlertSummaryQuery('ws-1'));
        self::assertSame(1, $summary1->totalActive);
        self::assertSame(1, $summary1->criticalCount);
        self::assertSame(0, $summary1->acknowledgedCount);

        // Acknowledge
        $ackDto = $ackHandler->handle(new AcknowledgeAlertCommand('ws-1', 'alt-1', 'user-1'));
        self::assertSame('acknowledged', $ackDto->status);
        self::assertSame('user-1', $ackDto->acknowledgedBy);
        self::assertNotNull($ackDto->acknowledgedAt);

        // Check summary after ack
        $summary2 = $summaryHandler->handle(new GetAlertSummaryQuery('ws-1'));
        self::assertSame(1, $summary2->totalActive);
        self::assertSame(1, $summary2->acknowledgedCount);

        // Resolve
        $resDto = $resolveHandler->handle(new ResolveAlertCommand('ws-1', 'alt-1', 'user-1', 'Решено оператором'));
        self::assertSame('resolved', $resDto->status);
        self::assertSame('Решено оператором', $resDto->resolutionNote);

        // Check summary after resolve
        $summary3 = $summaryHandler->handle(new GetAlertSummaryQuery('ws-1'));
        self::assertSame(0, $summary3->totalActive);

        // List resolved
        $paginated = $getAlertsHandler->handle(new GetAlertsQuery('ws-1', status: 'resolved'));
        self::assertCount(1, $paginated->items);
        self::assertSame('resolved', $paginated->items[0]->status);
    }

    public function test_not_found_throws_exception(): void
    {
        $getByIdHandler = new GetAlertByIdHandler($this->alertRepo);
        $this->expectException(AlertNotFoundException::class);
        $getByIdHandler->handle(new GetAlertByIdQuery('ws-1', 'non-existent'));
    }
}
