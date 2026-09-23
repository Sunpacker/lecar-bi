<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

use App\Modules\Alerting\Application\Dtos\AlertSummaryDto;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;

final class GetAlertSummaryHandler
{
    public function __construct(
        private AlertRepositoryInterface $alertRepository,
    ) {}

    public function handle(GetAlertSummaryQuery $query): AlertSummaryDto
    {
        $counts = $this->alertRepository->getSummaryCounts($query->workspaceId);

        return new AlertSummaryDto(
            totalActive: $counts['total_active'],
            criticalCount: $counts['critical_count'],
            warningCount: $counts['warning_count'],
            infoCount: $counts['info_count'],
            acknowledgedCount: $counts['acknowledged_count'],
        );
    }
}
