<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

use App\Modules\Alerting\Application\Dtos\AlertRuleDto;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\Exceptions\AlertRuleNotFoundException;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;

final class GetAlertRuleByIdHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
    ) {}

    public function handle(GetAlertRuleByIdQuery $query): AlertRuleDto
    {
        $rule = $this->ruleRepository->findById($query->workspaceId, new AlertRuleId($query->id));
        if ($rule === null) {
            throw AlertRuleNotFoundException::withId($query->id);
        }

        return AlertRuleDto::fromDomain($rule);
    }
}
