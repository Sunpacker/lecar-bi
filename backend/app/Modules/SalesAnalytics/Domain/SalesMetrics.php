<?php

namespace App\Modules\SalesAnalytics\Domain;

final class SalesMetrics
{
    public static function calculateAov(float $revenue, int $orderCount): float
    {
        if ($orderCount <= 0) {
            return 0.0;
        }

        return round($revenue / $orderCount, 2);
    }

    public static function calculateMarginRate(float $revenue, float $grossProfit): float
    {
        if ($revenue <= 0.0) {
            return 0.0;
        }

        return round($grossProfit / $revenue, 4);
    }

    public static function calculateShare(float $part, float $total): float
    {
        if ($total <= 0.0) {
            return 0.0;
        }

        return round($part / $total, 4);
    }
}
