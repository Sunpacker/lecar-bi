<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

final readonly class DeleteAlertRuleCommand
{
    public function __construct(
        public string $workspaceId,
        public string $id,
    ) {}
}
