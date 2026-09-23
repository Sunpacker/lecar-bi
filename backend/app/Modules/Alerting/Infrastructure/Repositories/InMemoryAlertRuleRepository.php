<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Repositories;

use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;

final class InMemoryAlertRuleRepository implements AlertRuleRepositoryInterface
{
    /** @var array<string, AlertRule> key is workspaceId:ruleId */
    private array $rules = [];

    public function save(AlertRule $rule): void
    {
        $key = $rule->workspaceId().':'.$rule->id()->value();
        $this->rules[$key] = $rule;
    }

    public function findById(string $workspaceId, AlertRuleId $id): ?AlertRule
    {
        $key = $workspaceId.':'.$id->value();

        return $this->rules[$key] ?? null;
    }

    /**
     * @return list<AlertRule>
     */
    public function findByWorkspaceId(string $workspaceId, ?bool $isEnabled = null): array
    {
        $result = [];
        foreach ($this->rules as $rule) {
            if ($rule->workspaceId() !== $workspaceId) {
                continue;
            }

            if ($isEnabled !== null && $rule->isEnabled() !== $isEnabled) {
                continue;
            }

            $result[] = $rule;
        }

        return $result;
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
        $key = $workspaceId.':'.$id->value();
        unset($this->rules[$key]);
    }
}
