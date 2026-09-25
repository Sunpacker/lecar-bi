<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

readonly class ForecastQualityDto
{
    public function __construct(
        public string $metricName,
        public float $metricValue,
        public int $horizonDays,
        public ?string $segment = null,
        public int $evaluationWindows = 0
    ) {}
}
