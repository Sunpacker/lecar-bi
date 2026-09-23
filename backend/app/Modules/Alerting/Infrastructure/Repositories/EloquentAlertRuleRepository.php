<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Repositories;

use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleCondition;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleScope;
use App\Modules\Alerting\Domain\RuleType;
use App\Modules\Alerting\Infrastructure\Models\AlertRuleModel;
use DateTimeImmutable;

final class EloquentAlertRuleRepository implements AlertRuleRepositoryInterface
{
    public function save(AlertRule $rule): void
    {
        AlertRuleModel::updateOrCreate(
            ['id' => $rule->id()->value(), 'workspace_id' => $rule->workspaceId()],
            [
                'name' => $rule->name(),
                'description' => $rule->description(),
                'rule_type' => $rule->ruleType()->value,
                'severity' => $rule->severity()->value,
                'metric' => $rule->condition()->metric()->value,
                'comparator' => $rule->condition()->comparator()->value,
                'threshold_value' => $rule->condition()->thresholdValue(),
                'warehouse_id' => $rule->scope()->warehouseId(),
                'category_id' => $rule->scope()->categoryId(),
                'product_id' => $rule->scope()->productId(),
                'is_enabled' => $rule->isEnabled(),
                'created_at' => $rule->createdAt()->format('Y-m-d H:i:s'),
                'updated_at' => $rule->updatedAt()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findById(string $workspaceId, AlertRuleId $id): ?AlertRule
    {
        $model = AlertRuleModel::where('workspace_id', $workspaceId)
            ->where('id', $id->value())
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * @return list<AlertRule>
     */
    public function findByWorkspaceId(string $workspaceId, ?bool $isEnabled = null): array
    {
        $query = AlertRuleModel::where('workspace_id', $workspaceId);

        if ($isEnabled !== null) {
            $query->where('is_enabled', $isEnabled);
        }

        return $query->orderBy('name')
            ->get()
            ->map(fn (AlertRuleModel $m) => $this->toDomain($m))
            ->all();
    }

    /**
     * @return list<AlertRule>
     */
    public function findEnabledByWorkspaceId(string $workspaceId): array
    {
        return $this->findByWorkspaceId($workspaceId, true);
    }

    public function delete(string $workspaceId, AlertRuleId $id): void
    {
        AlertRuleModel::where('workspace_id', $workspaceId)
            ->where('id', $id->value())
            ->delete();
    }

    private function toDomain(AlertRuleModel $model): AlertRule
    {
        return new AlertRule(
            id: new AlertRuleId((string) $model->id),
            workspaceId: (string) $model->workspace_id,
            name: (string) $model->name,
            description: $model->description !== null ? (string) $model->description : null,
            ruleType: RuleType::from((string) $model->rule_type),
            severity: AlertSeverity::from((string) $model->severity),
            condition: new RuleCondition(
                metric: RuleMetric::from((string) $model->metric),
                comparator: RuleComparator::from((string) $model->comparator),
                thresholdValue: (float) $model->threshold_value,
            ),
            scope: new RuleScope(
                warehouseId: $model->warehouse_id !== null ? (string) $model->warehouse_id : null,
                categoryId: $model->category_id !== null ? (string) $model->category_id : null,
                productId: $model->product_id !== null ? (string) $model->product_id : null,
            ),
            isEnabled: (bool) $model->is_enabled,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: new DateTimeImmutable($model->updated_at->toDateTimeString()),
        );
    }
}
