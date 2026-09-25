<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

readonly class DataQualityAssessment
{
    public function __construct(
        public int $totalDays,
        public int $stockoutDays,
        public float $stockoutRatio,
        public bool $hasMinHistory,
        public ForecastStatus $recommendedStatus
    ) {}

    public static function fromTimeSeries(SalesTimeSeries $series, int $minDays = 56): self
    {
        $totalDays = $series->totalDays();
        $stockoutDays = $series->stockoutDays();
        $stockoutRatio = $series->stockoutRatio();
        $hasMin = $totalDays >= $minDays;

        if (! $hasMin) {
            $status = ForecastStatus::InsufficientData;
        } elseif ($stockoutRatio > 0.5) {
            $status = ForecastStatus::LimitedByStockouts;
        } else {
            $status = ForecastStatus::Ready;
        }

        return new self(
            totalDays: $totalDays,
            stockoutDays: $stockoutDays,
            stockoutRatio: $stockoutRatio,
            hasMinHistory: $hasMin,
            recommendedStatus: $status
        );
    }
}
