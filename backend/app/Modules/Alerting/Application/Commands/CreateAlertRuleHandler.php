<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

use App\Modules\Alerting\Application\Dtos\AlertRuleDto;
use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleCondition;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleScope;
use App\Modules\Alerting\Domain\RuleType;
use DateTimeImmutable;

final class CreateAlertRuleHandler
{
    public function __construct(
        private AlertRuleRepositoryInterface $ruleRepository,
    ) {}

    public function handle(CreateAlertRuleCommand $command): AlertRuleDto
    {
        $id = $command->id !== null && trim($command->id) !== ''
            ? new AlertRuleId($command->id)
            : new AlertRuleId(sprintf('rule-%s', bin2hex(random_bytes(8))));

        $now = new DateTimeImmutable;

        $rule = new AlertRule(
            id: $id,
            workspaceId: $command->workspaceId,
            name: $command->name,
            description: $command->description,
            ruleType: RuleType::from($command->ruleType),
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
            createdAt: $now,
            updatedAt: $now,
        );

        $this->ruleRepository->save($rule);

        return AlertRuleDto::fromDomain($rule);
    }
}
