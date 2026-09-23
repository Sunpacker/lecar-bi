<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Repositories;

use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;

interface AlertRuleRepositoryInterface
{
    public function save(AlertRule $rule): void;

    public function findById(string $workspaceId, AlertRuleId $id): ?AlertRule;

    /** @return list<AlertRule> */
    public function findByWorkspaceId(string $workspaceId, ?bool $isEnabled = null): array;

    /** @return list<AlertRule> */
    public function findEnabledByWorkspaceId(string $workspaceId): array;

    public function delete(string $workspaceId, AlertRuleId $id): void;
}
