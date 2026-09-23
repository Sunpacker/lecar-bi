<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

use App\Modules\Alerting\Application\Contracts\InventoryAlertSourceInterface;
use App\Modules\Alerting\Application\Dtos\AlertEvaluationResultDto;
use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertStatus;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleType;
use DateTimeImmutable;

final class EvaluateAlertRulesHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
        private AlertRepositoryInterface $alertRepository,
        private InventoryAlertSourceInterface $inventorySource,
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
                    $existingActiveAlert->retrigger($actualMetric, $now);
                    $this->alertRepository->save($existingActiveAlert);
                    $alertsUpdated++;
                } else {
                    $newAlertId = new AlertId(sprintf('alt-%s', bin2hex(random_bytes(10))));
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

                    $newAlert = new Alert(
                        id: $newAlertId,
                        workspaceId: $command->workspaceId,
                        ruleId: $rule->id(),
                        ruleName: $rule->name(),
                        severity: $rule->severity(),
                        status: AlertStatus::OPEN,
                        dedupFingerprint: $fingerprint,
                        context: $alertContext,
                        triggeredAt: $now,
                        createdAt: $now,
                        updatedAt: $now,
                    );

                    $this->alertRepository->save($newAlert);
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
