<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use DateInterval;

class StockRiskCalculator
{
    /**
     * @param  ForecastPointValue[]  $forecastPoints
     */
    public function calculate(
        array $forecastPoints,
        int $currentAvailable,
        int $safetyStock,
        int $reorderPoint,
        ?int $medianLeadTimeDays
    ): StockRiskResult {
        $stock = (float) $currentAvailable;
        $depletionDate = null;
        $reorderThresholdDate = null;
        $orderPlacementDate = null;

        foreach ($forecastPoints as $point) {
            $stock -= $point->pointEstimate;

            if ($stock <= $reorderPoint && $reorderThresholdDate === null) {
                $reorderThresholdDate = $point->date;
            }

            if ($stock <= 0 && $depletionDate === null) {
                $depletionDate = $point->date;
            }

            if ($depletionDate !== null && $reorderThresholdDate !== null) {
                break;
            }
        }

        if ($reorderThresholdDate !== null && $medianLeadTimeDays !== null) {
            $orderPlacementDate = $reorderThresholdDate->sub(new DateInterval("P{$medianLeadTimeDays}D"));
        }

        return new StockRiskResult(
            currentQuantityAvailable: $currentAvailable,
            currentSafetyStock: $safetyStock,
            currentReorderPoint: $reorderPoint,
            estimatedDepletionDate: $depletionDate,
            estimatedReorderThresholdDate: $reorderThresholdDate,
            estimatedOrderPlacementDate: $orderPlacementDate,
            medianLeadTimeDays: $medianLeadTimeDays,
            leadTimeSource: $medianLeadTimeDays !== null ? 'historical' : null
        );
    }
}
