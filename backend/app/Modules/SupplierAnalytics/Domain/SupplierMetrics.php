<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Domain;

final class SupplierMetrics
{
    public static function calculateOnTimeRate(int $onTimeDeliveries, int $totalDeliveries): float
    {
        if ($totalDeliveries <= 0) {
            return 0.0;
        }

        return round(($onTimeDeliveries / $totalDeliveries) * 100, 1);
    }

    public static function calculateDelayRate(int $delayedDeliveries, int $totalDeliveries): float
    {
        if ($totalDeliveries <= 0) {
            return 0.0;
        }

        return round(($delayedDeliveries / $totalDeliveries) * 100, 1);
    }

    public static function calculateFulfillmentRate(int $receivedQuantity, int $orderedQuantity): float
    {
        if ($orderedQuantity <= 0) {
            return 0.0;
        }

        $rate = min(1.0, $receivedQuantity / $orderedQuantity) * 100;

        return round($rate, 1);
    }

    public static function calculateDefectRate(int $defectQuantity, int $receivedQuantity): float
    {
        if ($receivedQuantity <= 0) {
            return 0.0;
        }

        return round(($defectQuantity / $receivedQuantity) * 100, 1);
    }

    public static function calculateAverageLeadTime(float|int $totalLeadTimeDays, int $deliveriesCount): float
    {
        if ($deliveriesCount <= 0) {
            return 0.0;
        }

        return round((float) $totalLeadTimeDays / $deliveriesCount, 1);
    }

    public static function calculateAverageDelayDays(float|int $totalDelayDays, int $delayedCount): float
    {
        if ($delayedCount <= 0) {
            return 0.0;
        }

        return round((float) $totalDelayDays / $delayedCount, 1);
    }

    public static function calculateReliabilityScore(
        float $onTimeRate,
        float $fulfillmentRate,
        float $defectRate,
    ): float {
        $score = (0.5 * ($onTimeRate / 100)) + (0.4 * ($fulfillmentRate / 100)) - (0.1 * ($defectRate / 100));
        $clamped = max(0.0, min(1.0, $score));

        return round($clamped, 2);
    }

    public static function classifyReliabilityTier(float $reliabilityScore): SupplierReliabilityTier
    {
        if ($reliabilityScore >= 0.90) {
            return SupplierReliabilityTier::EXCELLENT;
        }

        if ($reliabilityScore >= 0.80) {
            return SupplierReliabilityTier::GOOD;
        }

        if ($reliabilityScore >= 0.70) {
            return SupplierReliabilityTier::ACCEPTABLE;
        }

        return SupplierReliabilityTier::POOR;
    }
}
