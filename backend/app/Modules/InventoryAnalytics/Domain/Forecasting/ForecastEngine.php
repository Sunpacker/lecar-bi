<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

class ForecastEngine
{
    public function __construct(
        private readonly ForecasterInterface $primary,
        private readonly ForecasterInterface $secondary,
        private readonly BacktestEngine $primaryBacktest,
        private readonly BacktestEngine $secondaryBacktest
    ) {}

    public function generate(SalesTimeSeries $series, ForecastHorizon $horizon): ForecastResult
    {
        $quality = DataQualityAssessment::fromTimeSeries($series);

        if ($quality->recommendedStatus === ForecastStatus::InsufficientData) {
            return new ForecastResult(
                status: ForecastStatus::InsufficientData,
                selectedMethod: $this->primary->method(),
                modelVersion: '1.0.0',
                points: [],
                backtestResult: new BacktestResult([], 0, $horizon),
                qualityAssessment: $quality,
                statusReason: 'Not enough historical data'
            );
        }

        $primaryResult = $this->primaryBacktest->evaluate($series, $horizon);
        $secondaryResult = $this->secondaryBacktest->evaluate($series, $horizon);

        $primaryWape = $primaryResult->metrics['wape'] ?? 1.0;
        $secondaryWape = $secondaryResult->metrics['wape'] ?? 1.0;

        if ($secondaryWape < $primaryWape) {
            $selectedForecaster = $this->secondary;
            $selectedBacktestResult = $secondaryResult;
        } else {
            $selectedForecaster = $this->primary;
            $selectedBacktestResult = $primaryResult;
        }

        $points = $selectedForecaster->forecast($series, $horizon);
        $intervals = $selectedBacktestResult->computePredictionIntervals();

        $calibratedPoints = [];
        foreach ($points as $i => $point) {
            $interval = $intervals[$i] ?? new PredictionInterval(0, 0, 0.80);
            $calibratedPoints[] = new ForecastPointValue(
                date: $point->date,
                pointEstimate: $point->pointEstimate,
                lowerBound: max(0.0, $point->pointEstimate + $interval->lower),
                upperBound: $point->pointEstimate + $interval->upper,
                intervalLevel: $interval->level
            );
        }

        return new ForecastResult(
            status: $quality->recommendedStatus,
            selectedMethod: $selectedForecaster->method(),
            modelVersion: '1.0.0',
            points: $calibratedPoints,
            backtestResult: $selectedBacktestResult,
            qualityAssessment: $quality,
            statusReason: null
        );
    }
}
