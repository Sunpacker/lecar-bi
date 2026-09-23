<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

use App\Modules\Alerting\Application\Dtos\AlertRuleDto;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;

final class GetAlertRulesHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
    ) {}

    /**
     * @return list<AlertRuleDto>
     */
    public function handle(GetAlertRulesQuery $query): array
    {
        $rules = $this->ruleRepository->findByWorkspaceId($query->workspaceId, $query->isEnabled);

        return array_map(fn ($r) => AlertRuleDto::fromDomain($r), $rules);
    }
}
