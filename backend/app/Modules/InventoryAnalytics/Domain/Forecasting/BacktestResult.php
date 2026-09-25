<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

readonly class BacktestResult
{
    /**
     * @param  array<string, float>  $metrics
     */
    public function __construct(
        public array $metrics,
        public int $evaluationWindows,
        public ForecastHorizon $horizon
    ) {}

    /**
     * @return array<int, PredictionInterval>
     */
    public function computePredictionIntervals(float $level = 0.80): array
    {
        $intervals = [];
        $width = $this->metrics['interval_width'] ?? 2.0;

        for ($i = 0; $i < $this->horizon->value; $i++) {
            $intervals[$i] = new PredictionInterval(
                lower: -($width / 2),
                upper: $width / 2,
                level: $level
            );
        }

        return $intervals;
    }
}
