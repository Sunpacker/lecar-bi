<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Repositories;

use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\AlertStatus;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;
use App\Modules\Alerting\Infrastructure\Models\AlertModel;
use DateTimeImmutable;

final class EloquentAlertRepository implements AlertRepositoryInterface
{
    public function save(Alert $alert): void
    {
        AlertModel::updateOrCreate(
            ['id' => $alert->id()->value(), 'workspace_id' => $alert->workspaceId()],
            [
                'rule_id' => $alert->ruleId()?->value(),
                'rule_name' => $alert->ruleName(),
                'severity' => $alert->severity()->value,
                'status' => $alert->status()->value,
                'dedup_fingerprint' => $alert->dedupFingerprint()->value(),
                'product_id' => $alert->context()->productId(),
                'product_name' => $alert->context()->productName(),
                'product_sku' => $alert->context()->productSku(),
                'warehouse_id' => $alert->context()->warehouseId(),
                'warehouse_name' => $alert->context()->warehouseName(),
                'current_value' => $alert->context()->currentValue(),
                'threshold_value' => $alert->context()->thresholdValue(),
                'context_data' => $alert->context()->toArray(),
                'triggered_at' => $alert->triggeredAt()->format('Y-m-d H:i:s'),
                'acknowledged_at' => $alert->acknowledgedAt()?->format('Y-m-d H:i:s'),
                'acknowledged_by' => $alert->acknowledgedBy(),
                'resolved_at' => $alert->resolvedAt()?->format('Y-m-d H:i:s'),
                'resolved_by' => $alert->resolvedBy(),
                'resolution_note' => $alert->resolutionNote(),
                'created_at' => $alert->createdAt()->format('Y-m-d H:i:s'),
                'updated_at' => $alert->updatedAt()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findById(string $workspaceId, AlertId $id): ?Alert
    {
        $model = AlertModel::where('workspace_id', $workspaceId)
            ->where('id', $id->value())
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findActiveByFingerprint(string $workspaceId, DedupFingerprint $fingerprint): ?Alert
    {
        $model = AlertModel::where('workspace_id', $workspaceId)
            ->where('dedup_fingerprint', $fingerprint->value())
            ->whereIn('status', [AlertStatus::OPEN->value, AlertStatus::ACKNOWLEDGED->value])
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * @return list<Alert>
     */
    public function listAlerts(
        string $workspaceId,
        ?string $status = null,
        ?AlertSeverity $severity = null,
        ?string $warehouseId = null,
        ?string $ruleId = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $query = AlertModel::where('workspace_id', $workspaceId);

        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', [AlertStatus::OPEN->value, AlertStatus::ACKNOWLEDGED->value]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($severity !== null) {
            $query->where('severity', $severity->value);
        }

        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($ruleId !== null && $ruleId !== '') {
            $query->where('rule_id', $ruleId);
        }

        $offset = max(0, ($page - 1) * $perPage);

        return $query->orderByDesc('triggered_at')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (AlertModel $m) => $this->toDomain($m))
            ->all();
    }

    public function countAlerts(
        string $workspaceId,
        ?string $status = null,
        ?AlertSeverity $severity = null,
        ?string $warehouseId = null,
        ?string $ruleId = null,
    ): int {
        $query = AlertModel::where('workspace_id', $workspaceId);

        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', [AlertStatus::OPEN->value, AlertStatus::ACKNOWLEDGED->value]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($severity !== null) {
            $query->where('severity', $severity->value);
        }

        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($ruleId !== null && $ruleId !== '') {
            $query->where('rule_id', $ruleId);
        }

        return $query->count();
    }

    /**
     * @return array{
     *     total_active: int,
     *     critical_count: int,
     *     warning_count: int,
     *     info_count: int,
     *     acknowledged_count: int,
     * }
     */
    public function getSummaryCounts(string $workspaceId): array
    {
        $activeRows = AlertModel::where('workspace_id', $workspaceId)
            ->whereIn('status', [AlertStatus::OPEN->value, AlertStatus::ACKNOWLEDGED->value])
            ->get(['status', 'severity']);

        $totalActive = $activeRows->count();
        $criticalCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        $acknowledgedCount = 0;

        foreach ($activeRows as $row) {
            if ($row->status === AlertStatus::ACKNOWLEDGED->value) {
                $acknowledgedCount++;
            }

            match ($row->severity) {
                AlertSeverity::CRITICAL->value => $criticalCount++,
                AlertSeverity::WARNING->value => $warningCount++,
                AlertSeverity::INFO->value => $infoCount++,
                default => null,
            };
        }

        return [
            'total_active' => $totalActive,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'acknowledged_count' => $acknowledgedCount,
        ];
    }

    private function toDomain(AlertModel $model): Alert
    {
        $contextData = is_array($model->context_data) ? $model->context_data : [];

        return new Alert(
            id: new AlertId((string) $model->id),
            workspaceId: (string) $model->workspace_id,
            ruleId: $model->rule_id !== null ? new AlertRuleId((string) $model->rule_id) : null,
            ruleName: (string) $model->rule_name,
            severity: AlertSeverity::from((string) $model->severity),
            status: AlertStatus::from((string) $model->status),
            dedupFingerprint: new DedupFingerprint((string) $model->dedup_fingerprint),
            context: AlertContext::fromArray(array_merge($contextData, [
                'warehouse_id' => $model->warehouse_id,
                'warehouse_name' => $model->warehouse_name,
                'product_id' => $model->product_id,
                'product_name' => $model->product_name,
                'product_sku' => $model->product_sku,
                'current_value' => $model->current_value !== null ? (float) $model->current_value : null,
                'threshold_value' => $model->threshold_value !== null ? (float) $model->threshold_value : null,
            ])),
            triggeredAt: new DateTimeImmutable($model->triggered_at->toDateTimeString()),
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: new DateTimeImmutable($model->updated_at->toDateTimeString()),
            acknowledgedAt: $model->acknowledged_at !== null ? new DateTimeImmutable($model->acknowledged_at->toDateTimeString()) : null,
            acknowledgedBy: $model->acknowledged_by !== null ? (string) $model->acknowledged_by : null,
            resolvedAt: $model->resolved_at !== null ? new DateTimeImmutable($model->resolved_at->toDateTimeString()) : null,
            resolvedBy: $model->resolved_by !== null ? (string) $model->resolved_by : null,
            resolutionNote: $model->resolution_note !== null ? (string) $model->resolution_note : null,
        );
    }
}
