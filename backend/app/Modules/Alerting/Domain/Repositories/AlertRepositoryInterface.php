<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Repositories;

use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\DedupFingerprint;

interface AlertRepositoryInterface
{
    public function save(Alert $alert): void;

    public function findById(string $workspaceId, AlertId $id): ?Alert;

    public function findActiveByFingerprint(string $workspaceId, DedupFingerprint $fingerprint): ?Alert;

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
    ): array;

    public function countAlerts(
        string $workspaceId,
        ?string $status = null,
        ?AlertSeverity $severity = null,
        ?string $warehouseId = null,
        ?string $ruleId = null,
    ): int;

    /**
     * @return array{
     *     total_active: int,
     *     critical_count: int,
     *     warning_count: int,
     *     info_count: int,
     *     acknowledged_count: int,
     * }
     */
    public function getSummaryCounts(string $workspaceId): array;
}
