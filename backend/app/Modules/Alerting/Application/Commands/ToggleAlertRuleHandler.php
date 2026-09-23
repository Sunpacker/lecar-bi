<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

use App\Modules\Alerting\Application\Dtos\AlertRuleDto;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\Exceptions\AlertRuleNotFoundException;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;

final class ToggleAlertRuleHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
    ) {}

    public function handle(ToggleAlertRuleCommand $command): AlertRuleDto
    {
        $rule = $this->ruleRepository->findById($command->workspaceId, new AlertRuleId($command->id));
        if ($rule === null) {
            throw AlertRuleNotFoundException::withId($command->id);
        }

        $rule->toggle();
        $this->ruleRepository->save($rule);

        return AlertRuleDto::fromDomain($rule);
    }
}
