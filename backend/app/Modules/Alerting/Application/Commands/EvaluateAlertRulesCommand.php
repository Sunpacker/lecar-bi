<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

final readonly class EvaluateAlertRulesCommand
{
    public function __construct(
        public string $workspaceId,
        public ?string $ruleId = null,
        public ?string $warehouseId = null,
    ) {}
}
