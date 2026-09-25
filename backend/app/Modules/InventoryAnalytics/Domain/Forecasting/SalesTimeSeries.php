<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use DateTimeImmutable;

readonly class SalesTimeSeries
{
    /**
     * @param  DailySalesPoint[]  $points
     */
    public function __construct(
        public string $productId,
        public string $warehouseId,
        public DateTimeImmutable $asOfDate,
        public array $points
    ) {}

    public function totalDays(): int
    {
        return count($this->points);
    }

    public function stockoutDays(): int
    {
        $count = 0;
        foreach ($this->points as $point) {
            if ($point->isStockoutDay) {
                $count++;
            }
        }

        return $count;
    }

    public function stockoutRatio(): float
    {
        $total = $this->totalDays();
        if ($total === 0) {
            return 0.0;
        }

        return $this->stockoutDays() / $total;
    }

    public function hasMinimumHistory(int $minDays = 28): bool
    {
        return $this->totalDays() >= $minDays;
    }

    public function averageDailySales(): float
    {
        $censored = $this->censoredSeries();
        if (count($censored) === 0) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($censored as $point) {
            $sum += $point->quantity;
        }

        return $sum / count($censored);
    }

    /**
     * @return DailySalesPoint[]
     */
    public function getPointsByDayOfWeek(int $dow): array
    {
        return array_values(array_filter($this->points, function (DailySalesPoint $point) use ($dow) {
            return (int) $point->date->format('N') === $dow;
        }));
    }

    /**
     * @return DailySalesPoint[]
     */
    public function censoredSeries(): array
    {
        return array_values(array_filter($this->points, function (DailySalesPoint $point) {
            return ! $point->isStockoutDay;
        }));
    }
}
