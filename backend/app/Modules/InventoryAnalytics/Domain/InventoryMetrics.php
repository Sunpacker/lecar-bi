<?php

namespace App\Modules\InventoryAnalytics\Domain;

final class InventoryMetrics
{
    public const CRITICAL_DOS_THRESHOLD = 7.0;

    public const OVERSTOCK_DOS_THRESHOLD = 60.0;

    public static function calculateSalesVelocity(int|float $unitsSold, int $days = 30): float
    {
        if ($days <= 0) {
            return round((float) $unitsSold, 2);
        }

        return round($unitsSold / $days, 2);
    }

    public static function calculateDaysOfStock(int $availableQuantity, float $dailyVelocity): ?float
    {
        if ($availableQuantity <= 0) {
            return 0.0;
        }

        if ($dailyVelocity <= 0.0) {
            return null;
        }

        return round($availableQuantity / $dailyVelocity, 1);
    }

    public static function classifyStockHealth(
        int $availableQuantity,
        float $dailyVelocity,
        ?float $daysOfStock,
        int $safetyStock,
        int $reorderPoint,
    ): StockHealthStatus {
        if ($availableQuantity <= 0) {
            return StockHealthStatus::OUT_OF_STOCK;
        }

        if (($daysOfStock !== null && $daysOfStock <= self::CRITICAL_DOS_THRESHOLD) || $availableQuantity <= $safetyStock) {
            return StockHealthStatus::CRITICAL;
        }

        if (($daysOfStock !== null && $daysOfStock > self::OVERSTOCK_DOS_THRESHOLD) || ($daysOfStock === null && $availableQuantity > $safetyStock * 3)) {
            return StockHealthStatus::OVERSTOCK;
        }

        return StockHealthStatus::OPTIMAL;
    }

    public static function calculateShare(float $part, float $total): float
    {
        if ($total <= 0.0) {
            return 0.0;
        }

        return round($part / $total, 4);
    }
}
