<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

use App\Modules\Alerting\Application\Dtos\AlertDto;
use App\Modules\Alerting\Application\Dtos\PaginatedAlertsDto;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;

final class GetAlertsHandler
{
    public function __construct(
        private AlertRepositoryInterface $alertRepository,
    ) {}

    public function handle(GetAlertsQuery $query): PaginatedAlertsDto
    {
        $severity = $query->severity !== null && $query->severity !== ''
            ? AlertSeverity::tryFrom($query->severity)
            : null;

        $items = $this->alertRepository->listAlerts(
            workspaceId: $query->workspaceId,
            status: $query->status,
            severity: $severity,
            warehouseId: $query->warehouseId,
            ruleId: $query->ruleId,
            page: $query->page,
            perPage: $query->perPage,
        );

        $total = $this->alertRepository->countAlerts(
            workspaceId: $query->workspaceId,
            status: $query->status,
            severity: $severity,
            warehouseId: $query->warehouseId,
            ruleId: $query->ruleId,
        );

        $totalPages = $query->perPage > 0 ? (int) ceil($total / $query->perPage) : 0;

        return new PaginatedAlertsDto(
            items: array_map(fn ($a) => AlertDto::fromDomain($a), $items),
            page: $query->page,
            perPage: $query->perPage,
            total: $total,
            totalPages: $totalPages,
        );
    }
}
