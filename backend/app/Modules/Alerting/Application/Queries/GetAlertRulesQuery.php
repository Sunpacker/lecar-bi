<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

final readonly class GetAlertRulesQuery
{
    public function __construct(
        public string $workspaceId,
        public ?bool $isEnabled = null,
    ) {}
}
