<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Repositories;

use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;

final class InMemoryAlertRepository implements AlertRepositoryInterface
{
    /** @var array<string, Alert> key is workspaceId:alertId */
    private array $alerts = [];

    public function save(Alert $alert): void
    {
        $key = $alert->workspaceId().':'.$alert->id()->value();
        $this->alerts[$key] = $alert;
    }

    public function findById(string $workspaceId, AlertId $id): ?Alert
    {
        $key = $workspaceId.':'.$id->value();

        return $this->alerts[$key] ?? null;
    }

    public function findActiveByFingerprint(string $workspaceId, DedupFingerprint $fingerprint): ?Alert
    {
        foreach ($this->alerts as $alert) {
            if ($alert->workspaceId() === $workspaceId
                && $alert->dedupFingerprint()->equals($fingerprint)
                && $alert->isActive()) {
                return $alert;
            }
        }

        return null;
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
        $filtered = array_values(array_filter(
            $this->alerts,
            function (Alert $alert) use ($workspaceId, $status, $severity, $warehouseId, $ruleId) {
                if ($alert->workspaceId() !== $workspaceId) {
                    return false;
                }

                if ($status !== null && $status !== '' && $status !== 'all') {
                    if ($status === 'active') {
                        if (! $alert->isActive()) {
                            return false;
                        }
                    } else {
                        if ($alert->status()->value !== $status) {
                            return false;
                        }
                    }
                }

                if ($severity !== null && $alert->severity() !== $severity) {
                    return false;
                }

                if ($warehouseId !== null && $warehouseId !== '' && $alert->context()->warehouseId() !== $warehouseId) {
                    return false;
                }

                if ($ruleId !== null && $ruleId !== '' && $alert->ruleId()?->value() !== $ruleId) {
                    return false;
                }

                return true;
            }
        ));

        // Sort desc by triggeredAt
        usort($filtered, fn (Alert $a, Alert $b) => $b->triggeredAt() <=> $a->triggeredAt());

        $offset = max(0, ($page - 1) * $perPage);

        return array_slice($filtered, $offset, $perPage);
    }

    public function countAlerts(
        string $workspaceId,
        ?string $status = null,
        ?AlertSeverity $severity = null,
        ?string $warehouseId = null,
        ?string $ruleId = null,
    ): int {
        $count = 0;
        foreach ($this->alerts as $alert) {
            if ($alert->workspaceId() !== $workspaceId) {
                continue;
            }

            if ($status !== null && $status !== '' && $status !== 'all') {
                if ($status === 'active') {
                    if (! $alert->isActive()) {
                        continue;
                    }
                } else {
                    if ($alert->status()->value !== $status) {
                        continue;
                    }
                }
            }

            if ($severity !== null && $alert->severity() !== $severity) {
                continue;
            }

            if ($warehouseId !== null && $warehouseId !== '' && $alert->context()->warehouseId() !== $warehouseId) {
                continue;
            }

            if ($ruleId !== null && $ruleId !== '' && $alert->ruleId()?->value() !== $ruleId) {
                continue;
            }

            $count++;
        }

        return $count;
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
        $totalActive = 0;
        $criticalCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        $acknowledgedCount = 0;

        foreach ($this->alerts as $alert) {
            if ($alert->workspaceId() !== $workspaceId || ! $alert->isActive()) {
                continue;
            }

            $totalActive++;

            if ($alert->isAcknowledged()) {
                $acknowledgedCount++;
            }

            match ($alert->severity()) {
                AlertSeverity::CRITICAL => $criticalCount++,
                AlertSeverity::WARNING => $warningCount++,
                AlertSeverity::INFO => $infoCount++,
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
}
