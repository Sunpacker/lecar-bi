<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

use App\Modules\Alerting\Application\Dtos\AlertRuleDto;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Exceptions\AlertRuleNotFoundException;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleCondition;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleScope;

final class UpdateAlertRuleHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
    ) {}

    public function handle(UpdateAlertRuleCommand $command): AlertRuleDto
    {
        $rule = $this->ruleRepository->findById($command->workspaceId, new AlertRuleId($command->id));
        if ($rule === null) {
            throw AlertRuleNotFoundException::withId($command->id);
        }

        $rule->update(
            name: $command->name,
            description: $command->description,
            severity: AlertSeverity::from($command->severity),
            condition: new RuleCondition(
                metric: RuleMetric::from($command->metric),
                comparator: RuleComparator::from($command->comparator),
                thresholdValue: $command->thresholdValue,
            ),
            scope: new RuleScope(
                warehouseId: $command->warehouseId,
                categoryId: $command->categoryId,
                productId: $command->productId,
            ),
            isEnabled: $command->isEnabled,
        );

        $this->ruleRepository->save($rule);

        return AlertRuleDto::fromDomain($rule);
    }
}
