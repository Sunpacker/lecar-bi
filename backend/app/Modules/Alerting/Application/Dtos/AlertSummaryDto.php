<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Dtos;

final readonly class AlertSummaryDto
{
    public function __construct(
        public int $totalActive,
        public int $criticalCount,
        public int $warningCount,
        public int $infoCount,
        public int $acknowledgedCount,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'total_active' => $this->totalActive,
            'critical_count' => $this->criticalCount,
            'warning_count' => $this->warningCount,
            'info_count' => $this->infoCount,
            'acknowledged_count' => $this->acknowledgedCount,
        ];
    }
}
