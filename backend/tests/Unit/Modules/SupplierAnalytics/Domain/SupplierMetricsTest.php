<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SupplierAnalytics\Domain;

use App\Modules\SupplierAnalytics\Domain\DeliveryStatus;
use App\Modules\SupplierAnalytics\Domain\SupplierMetrics;
use App\Modules\SupplierAnalytics\Domain\SupplierReliabilityTier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupplierMetricsTest extends TestCase
{
    #[Test]
    public function calculates_on_time_rate_correctly(): void
    {
        self::assertSame(85.0, SupplierMetrics::calculateOnTimeRate(85, 100));
        self::assertSame(100.0, SupplierMetrics::calculateOnTimeRate(10, 10));
        self::assertSame(0.0, SupplierMetrics::calculateOnTimeRate(0, 50));
        self::assertSame(0.0, SupplierMetrics::calculateOnTimeRate(0, 0));
        self::assertSame(33.3, SupplierMetrics::calculateOnTimeRate(1, 3));
    }

    #[Test]
    public function calculates_delay_rate_correctly(): void
    {
        self::assertSame(15.0, SupplierMetrics::calculateDelayRate(15, 100));
        self::assertSame(0.0, SupplierMetrics::calculateDelayRate(0, 100));
        self::assertSame(100.0, SupplierMetrics::calculateDelayRate(5, 5));
        self::assertSame(0.0, SupplierMetrics::calculateDelayRate(0, 0));
    }

    #[Test]
    public function calculates_fulfillment_rate_correctly(): void
    {
        self::assertSame(95.0, SupplierMetrics::calculateFulfillmentRate(950, 1000));
        self::assertSame(100.0, SupplierMetrics::calculateFulfillmentRate(100, 100));
        self::assertSame(0.0, SupplierMetrics::calculateFulfillmentRate(0, 100));
        self::assertSame(0.0, SupplierMetrics::calculateFulfillmentRate(0, 0));
        self::assertSame(100.0, SupplierMetrics::calculateFulfillmentRate(110, 100)); // over-delivered capped at 100%
    }

    #[Test]
    public function calculates_defect_rate_correctly(): void
    {
        self::assertSame(1.2, SupplierMetrics::calculateDefectRate(12, 1000));
        self::assertSame(0.0, SupplierMetrics::calculateDefectRate(0, 500));
        self::assertSame(0.0, SupplierMetrics::calculateDefectRate(0, 0));
        self::assertSame(2.5, SupplierMetrics::calculateDefectRate(25, 1000));
    }

    #[Test]
    public function calculates_average_lead_time_correctly(): void
    {
        self::assertSame(7.0, SupplierMetrics::calculateAverageLeadTime(70, 10));
        self::assertSame(4.5, SupplierMetrics::calculateAverageLeadTime(9, 2));
        self::assertSame(0.0, SupplierMetrics::calculateAverageLeadTime(0, 0));
    }

    #[Test]
    public function calculates_average_delay_days_correctly(): void
    {
        self::assertSame(3.0, SupplierMetrics::calculateAverageDelayDays(15, 5));
        self::assertSame(0.0, SupplierMetrics::calculateAverageDelayDays(0, 0));
        self::assertSame(2.5, SupplierMetrics::calculateAverageDelayDays(5, 2));
    }

    #[Test]
    public function calculates_reliability_score_and_tier_classification(): void
    {
        // 95% on-time, 98% fulfillment, 0.5% defect
        // 0.5 * 0.95 + 0.4 * 0.98 - 0.1 * 0.005 = 0.475 + 0.392 - 0.0005 = 0.8665 -> 0.87 (GOOD)
        $score1 = SupplierMetrics::calculateReliabilityScore(95.0, 98.0, 0.5);
        self::assertSame(0.87, $score1);
        self::assertSame(SupplierReliabilityTier::GOOD, SupplierMetrics::classifyReliabilityTier($score1));

        // 100% on-time, 100% fulfillment, 0% defect
        // 0.5 * 1.0 + 0.4 * 1.0 - 0 = 0.90 -> EXCELLENT
        $score2 = SupplierMetrics::calculateReliabilityScore(100.0, 100.0, 0.0);
        self::assertSame(0.9, $score2);
        self::assertSame(SupplierReliabilityTier::EXCELLENT, SupplierMetrics::classifyReliabilityTier($score2));

        // 70% on-time, 80% fulfillment, 3% defect
        // 0.5 * 0.7 + 0.4 * 0.8 - 0.1 * 0.03 = 0.35 + 0.32 - 0.003 = 0.667 -> 0.67 (POOR)
        $score3 = SupplierMetrics::calculateReliabilityScore(70.0, 80.0, 3.0);
        self::assertSame(0.67, $score3);
        self::assertSame(SupplierReliabilityTier::POOR, SupplierMetrics::classifyReliabilityTier($score3));

        // 75% on-time, 90% fulfillment, 1% defect
        // 0.5 * 0.75 + 0.4 * 0.9 - 0.1 * 0.01 = 0.375 + 0.36 - 0.001 = 0.734 -> 0.73 (ACCEPTABLE)
        $score4 = SupplierMetrics::calculateReliabilityScore(75.0, 90.0, 1.0);
        self::assertSame(0.73, $score4);
        self::assertSame(SupplierReliabilityTier::ACCEPTABLE, SupplierMetrics::classifyReliabilityTier($score4));
    }

    #[Test]
    public function enums_have_expected_values(): void
    {
        self::assertSame('on_time', DeliveryStatus::ON_TIME->value);
        self::assertSame('delayed', DeliveryStatus::DELAYED->value);
        self::assertSame('partial', DeliveryStatus::PARTIAL->value);

        self::assertSame('excellent', SupplierReliabilityTier::EXCELLENT->value);
        self::assertSame('good', SupplierReliabilityTier::GOOD->value);
        self::assertSame('acceptable', SupplierReliabilityTier::ACCEPTABLE->value);
        self::assertSame('poor', SupplierReliabilityTier::POOR->value);
    }
}
