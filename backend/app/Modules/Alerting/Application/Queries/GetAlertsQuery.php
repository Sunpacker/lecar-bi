<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

final readonly class GetAlertsQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $status = 'active',
        public ?string $severity = null,
        public ?string $warehouseId = null,
        public ?string $ruleId = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
