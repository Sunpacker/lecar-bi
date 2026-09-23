<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

use App\Modules\Alerting\Application\Contracts\InventoryAlertSourceInterface;
use App\Modules\Alerting\Application\Dtos\AlertEvaluationResultDto;
use App\Modules\Alerting\Application\Mappers\AlertTriggeredIntegrationMapper;
use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Events\AlertTriggered;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleType;
use App\Shared\Application\Ports\OutboxRepositoryInterface;
use App\Shared\Application\Ports\TransactionManagerInterface;
use App\Shared\Domain\DomainEventId;
use DateTimeImmutable;

final class EvaluateAlertRulesHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
        private AlertRepositoryInterface $alertRepository,
        private InventoryAlertSourceInterface $inventorySource,
        private ?OutboxRepositoryInterface $outboxRepository = null,
        private ?TransactionManagerInterface $transactionManager = null,
        private ?AlertTriggeredIntegrationMapper $mapper = null,
    ) {}

    public function handle(EvaluateAlertRulesCommand $command): AlertEvaluationResultDto
    {
        if ($command->ruleId !== null) {
            $rule = $this->ruleRepository->findById($command->workspaceId, new AlertRuleId($command->ruleId));
            $rules = ($rule !== null && $rule->isEnabled()) ? [$rule] : [];
        } else {
            $rules = $this->ruleRepository->findEnabledByWorkspaceId($command->workspaceId);
        }

        $rulesEvaluated = count($rules);
        if ($rulesEvaluated === 0) {
            return new AlertEvaluationResultDto(0, 0, 0, 0);
        }

        $candidates = $this->inventorySource->getInventoryCandidates(
            $command->workspaceId,
            $command->warehouseId
        );

        $alertsTriggered = 0;
        $alertsCreated = 0;
        $alertsUpdated = 0;
        $now = new DateTimeImmutable;

        foreach ($rules as $rule) {
            foreach ($candidates as $candidate) {
                if (! $rule->scope()->matches($candidate->warehouseId, $candidate->categoryId, $candidate->productId)) {
                    continue;
                }

                $actualMetric = match ($rule->condition()->metric()) {
                    RuleMetric::QUANTITY_AVAILABLE => (float) $candidate->quantityAvailable,
                    RuleMetric::DAYS_OF_STOCK => (float) ($candidate->daysOfStock ?? 0.0),
                    RuleMetric::INVENTORY_VALUE => (float) $candidate->inventoryValue,
                };

                if ($rule->ruleType() === RuleType::REORDER_POINT) {
                    $triggered = $candidate->quantityAvailable <= $candidate->reorderPoint;
                    $threshold = (float) $candidate->reorderPoint;
                } elseif ($rule->ruleType() === RuleType::OUT_OF_STOCK) {
                    $triggered = $candidate->quantityAvailable <= 0;
                    $threshold = 0.0;
                } elseif ($rule->ruleType() === RuleType::CRITICAL_STOCK && $rule->condition()->metric() === RuleMetric::QUANTITY_AVAILABLE) {
                    $triggered = $candidate->quantityAvailable <= $candidate->safetyStock;
                    $threshold = (float) $candidate->safetyStock;
                } else {
                    $triggered = $rule->condition()->isSatisfied($actualMetric);
                    $threshold = $rule->condition()->thresholdValue();
                }

                if (! $triggered) {
                    continue;
                }

                $alertsTriggered++;

                // Deterministic Deduplication:
                $fingerprint = DedupFingerprint::generate(
                    $command->workspaceId,
                    $rule->id()->value(),
                    $candidate->productId,
                    $candidate->warehouseId
                );

                $existingActiveAlert = $this->alertRepository->findActiveByFingerprint(
                    $command->workspaceId,
                    $fingerprint
                );

                if ($existingActiveAlert !== null) {
                    // Retrigger existing active alert (update value and timestamp) without creating duplicate!
                    // retrigger() does NOT record a domain event — expected behavior.
                    $existingActiveAlert->retrigger($actualMetric, $now);
                    $this->alertRepository->save($existingActiveAlert);
                    $alertsUpdated++;
                } else {
                    // New alert cycle: save alert + register outbox message atomically.
                    $newAlertId = new AlertId(sprintf('alt-%s', bin2hex(random_bytes(10))));
                    $eventId = DomainEventId::generate();
                    $alertContext = new AlertContext(
                        target: 'inventory',
                        warehouseId: $candidate->warehouseId,
                        warehouseName: $candidate->warehouseName,
                        productId: $candidate->productId,
                        productName: $candidate->productName,
                        productSku: $candidate->productSku,
                        currentValue: $actualMetric,
                        thresholdValue: $threshold,
                    );

                    $newAlert = Alert::trigger(
                        id: $newAlertId,
                        eventId: $eventId,
                        workspaceId: $command->workspaceId,
                        ruleId: $rule->id(),
                        ruleName: $rule->name(),
                        severity: $rule->severity(),
                        dedupFingerprint: $fingerprint,
                        context: $alertContext,
                        metric: $rule->condition()->metric(),
                        comparator: $rule->condition()->comparator(),
                        now: $now,
                    );

                    if ($this->transactionManager !== null && $this->outboxRepository !== null && $this->mapper !== null) {
                        $this->transactionManager->transaction(function () use ($newAlert): void {
                            $this->alertRepository->save($newAlert);

                            foreach ($newAlert->releaseDomainEvents() as $domainEvent) {
                                if ($domainEvent instanceof AlertTriggered) {
                                    $integrationEvent = $this->mapper->map($domainEvent);
                                    $this->outboxRepository->register($integrationEvent);
                                }
                            }
                        });
                    } else {
                        $this->alertRepository->save($newAlert);
                    }

                    $alertsCreated++;
                }
            }
        }

        return new AlertEvaluationResultDto(
            rulesEvaluated: $rulesEvaluated,
            alertsTriggered: $alertsTriggered,
            alertsCreated: $alertsCreated,
            alertsUpdated: $alertsUpdated,
        );
    }
}
