<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\Exceptions\AlertRuleNotFoundException;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;

final class DeleteAlertRuleHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
    ) {}

    public function handle(DeleteAlertRuleCommand $command): void
    {
        $id = new AlertRuleId($command->id);
        $rule = $this->ruleRepository->findById($command->workspaceId, $id);
        if ($rule === null) {
            throw AlertRuleNotFoundException::withId($command->id);
        }

        $this->ruleRepository->delete($command->workspaceId, $id);
    }
}
