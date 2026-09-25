<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

readonly class ForecastResult
{
    /**
     * @param  ForecastPointValue[]  $points
     */
    public function __construct(
        public ForecastStatus $status,
        public ForecastMethod $selectedMethod,
        public string $modelVersion,
        public array $points,
        public BacktestResult $backtestResult,
        public DataQualityAssessment $qualityAssessment,
        public ?string $statusReason
    ) {}
}
