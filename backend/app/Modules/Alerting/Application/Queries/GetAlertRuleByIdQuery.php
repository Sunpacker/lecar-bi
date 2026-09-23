<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

final readonly class GetAlertRuleByIdQuery
{
    public function __construct(
        public string $workspaceId,
        public string $id,
    ) {}
}
