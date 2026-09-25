<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InventoryAnalytics\Domain;

use App\Modules\InventoryAnalytics\Domain\Forecasting\BacktestEngine;
use App\Modules\InventoryAnalytics\Domain\Forecasting\DailySalesPoint;
use App\Modules\InventoryAnalytics\Domain\Forecasting\DataQualityAssessment;
use App\Modules\InventoryAnalytics\Domain\Forecasting\ForecastEngine;
use App\Modules\InventoryAnalytics\Domain\Forecasting\ForecastHorizon;
use App\Modules\InventoryAnalytics\Domain\Forecasting\ForecastMethod;
use App\Modules\InventoryAnalytics\Domain\Forecasting\ForecastStatus;
use App\Modules\InventoryAnalytics\Domain\Forecasting\MovingAverageForecaster;
use App\Modules\InventoryAnalytics\Domain\Forecasting\PredictionInterval;
use App\Modules\InventoryAnalytics\Domain\Forecasting\SalesTimeSeries;
use App\Modules\InventoryAnalytics\Domain\Forecasting\SeasonalNaiveForecaster;
use App\Modules\InventoryAnalytics\Domain\Forecasting\StockRiskCalculator;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ForecastingTest extends TestCase
{
    private function createSeries(int $days, float $baseQty = 10.0, int $stockoutEvery = 0): SalesTimeSeries
    {
        $start = new DateTimeImmutable('2025-01-01');
        $points = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->add(new DateInterval("P{$i}D"));
            $isStockout = $stockoutEvery > 0 && ($i % $stockoutEvery === 0);
            $qty = $isStockout ? 0.0 : $baseQty + ($i % 7);
            $points[] = new DailySalesPoint($date, $qty, $isStockout);
        }

        $asOfDate = $start->add(new DateInterval('P'.($days - 1).'D'));

        return new SalesTimeSeries('prod-1', 'wh-1', $asOfDate, $points);
    }

    public function test_sales_time_series_calculations(): void
    {
        $series = $this->createSeries(60, 10.0, 5); // every 5th day is stockout: 12 stockout days

        $this->assertSame(60, $series->totalDays());
        $this->assertSame(12, $series->stockoutDays());
        $this->assertEqualsWithDelta(0.20, $series->stockoutRatio(), 0.01);
        $this->assertTrue($series->hasMinimumHistory(28));
        $this->assertFalse($series->hasMinimumHistory(90));

        $censored = $series->censoredSeries();
        $this->assertCount(48, $censored);
        $this->assertGreaterThan(0.0, $series->averageDailySales());
    }

    public function test_data_quality_assessment(): void
    {
        // 1. Insufficient history (< 56 days)
        $shortSeries = $this->createSeries(30);
        $qualityShort = DataQualityAssessment::fromTimeSeries($shortSeries);
        $this->assertSame(ForecastStatus::InsufficientData, $qualityShort->recommendedStatus);
        $this->assertFalse($qualityShort->hasMinHistory);

        // 2. High stockout (> 50%)
        $start = new DateTimeImmutable('2025-01-01');
        $points = [];
        for ($i = 0; $i < 60; $i++) {
            $isStockout = ($i % 3 !== 0); // 40 stockouts out of 60 (66.7% > 50%)
            $points[] = new DailySalesPoint($start->add(new DateInterval("P{$i}D")), $isStockout ? 0.0 : 10.0, $isStockout);
        }
        $stockoutSeries = new SalesTimeSeries('prod-1', 'wh-1', $start->add(new DateInterval('P59D')), $points);
        $qualityStockout = DataQualityAssessment::fromTimeSeries($stockoutSeries);
        $this->assertSame(ForecastStatus::LimitedByStockouts, $qualityStockout->recommendedStatus);

        // 3. Ready
        $goodSeries = $this->createSeries(60, 10.0, 10); // 10% stockout
        $qualityGood = DataQualityAssessment::fromTimeSeries($goodSeries);
        $this->assertSame(ForecastStatus::Ready, $qualityGood->recommendedStatus);
    }

    public function test_seasonal_naive_forecaster(): void
    {
        $series = $this->createSeries(70, 10.0);
        $forecaster = new SeasonalNaiveForecaster;

        $this->assertSame(ForecastMethod::SeasonalNaiveDow, $forecaster->method());

        $forecast = $forecaster->forecast($series, ForecastHorizon::Week);
        $this->assertCount(7, $forecast);

        foreach ($forecast as $point) {
            $this->assertGreaterThan(0.0, $point->pointEstimate);
            $this->assertNull($point->lowerBound);
            $this->assertNull($point->upperBound);
        }
    }

    public function test_moving_average_forecaster(): void
    {
        $series = $this->createSeries(70, 15.0);
        $forecaster = new MovingAverageForecaster;

        $this->assertSame(ForecastMethod::MovingAverage28d, $forecaster->method());

        $forecast = $forecaster->forecast($series, ForecastHorizon::TwoWeeks);
        $this->assertCount(14, $forecast);

        $firstEstimate = $forecast[0]->pointEstimate;
        foreach ($forecast as $point) {
            $this->assertSame($firstEstimate, $point->pointEstimate);
        }
    }

    public function test_prediction_interval(): void
    {
        $interval = new PredictionInterval(-2.5, 3.5, 0.80);
        $this->assertEqualsWithDelta(6.0, $interval->width(), 0.001);
        $this->assertSame(0.80, $interval->level);
    }

    public function test_backtest_engine_evaluates_rolling_origin(): void
    {
        $series = $this->createSeries(90, 10.0);
        $forecaster = new SeasonalNaiveForecaster;
        $engine = new BacktestEngine($forecaster, 3);

        $result = $engine->evaluate($series, ForecastHorizon::Week);

        $this->assertGreaterThan(0, $result->evaluationWindows);
        $this->assertArrayHasKey('mae', $result->metrics);
        $this->assertArrayHasKey('wape', $result->metrics);
        $this->assertArrayHasKey('signed_bias', $result->metrics);
        $this->assertArrayHasKey('coverage', $result->metrics);
        $this->assertArrayHasKey('interval_width', $result->metrics);

        $intervals = $result->computePredictionIntervals(0.80);
        $this->assertCount(7, $intervals);
    }

    public function test_stock_risk_calculator(): void
    {
        $series = $this->createSeries(60, 10.0);
        $forecaster = new MovingAverageForecaster;
        $points = $forecaster->forecast($series, ForecastHorizon::Month); // 28 days

        $calculator = new StockRiskCalculator;

        // Current stock = 100, Daily usage approx 18 -> depletion in ~5-6 days
        $risk = $calculator->calculate(
            forecastPoints: $points,
            currentAvailable: 100,
            safetyStock: 30,
            reorderPoint: 50,
            medianLeadTimeDays: 3
        );

        $this->assertSame(100, $risk->currentQuantityAvailable);
        $this->assertSame(30, $risk->currentSafetyStock);
        $this->assertSame(50, $risk->currentReorderPoint);
        $this->assertNotNull($risk->estimatedDepletionDate);
        $this->assertNotNull($risk->estimatedReorderThresholdDate);
        $this->assertNotNull($risk->estimatedOrderPlacementDate);
        $this->assertSame(3, $risk->medianLeadTimeDays);
        $this->assertSame('historical', $risk->leadTimeSource);
    }

    public function test_forecast_engine_full_workflow(): void
    {
        $seasonal = new SeasonalNaiveForecaster;
        $movingAvg = new MovingAverageForecaster;
        $engine = new ForecastEngine(
            primary: $seasonal,
            secondary: $movingAvg,
            primaryBacktest: new BacktestEngine($seasonal, 2),
            secondaryBacktest: new BacktestEngine($movingAvg, 2)
        );

        // 1. With sufficient data
        $series = $this->createSeries(90, 10.0);
        $result = $engine->generate($series, ForecastHorizon::TwoWeeks);

        $this->assertSame(ForecastStatus::Ready, $result->status);
        $this->assertCount(14, $result->points);
        $this->assertNotNull($result->points[0]->lowerBound);
        $this->assertNotNull($result->points[0]->upperBound);
        $this->assertGreaterThanOrEqual(0.0, $result->points[0]->lowerBound);
        $this->assertNotNull($result->backtestResult);

        // 2. With insufficient data
        $shortSeries = $this->createSeries(20, 10.0);
        $shortResult = $engine->generate($shortSeries, ForecastHorizon::Week);
        $this->assertSame(ForecastStatus::InsufficientData, $shortResult->status);
        $this->assertEmpty($shortResult->points);
    }
}
