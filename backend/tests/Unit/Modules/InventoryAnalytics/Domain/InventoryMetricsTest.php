<?php

namespace Tests\Unit\Modules\InventoryAnalytics\Domain;

use App\Modules\InventoryAnalytics\Domain\InventoryMetrics;
use App\Modules\InventoryAnalytics\Domain\StockHealthStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InventoryMetricsTest extends TestCase
{
    #[Test]
    public function calculate_sales_velocity_returns_correct_daily_rate(): void
    {
        self::assertSame(2.0, InventoryMetrics::calculateSalesVelocity(60, 30));
        self::assertSame(0.0, InventoryMetrics::calculateSalesVelocity(0, 30));
        self::assertSame(0.5, InventoryMetrics::calculateSalesVelocity(15, 30));
        self::assertSame(10.0, InventoryMetrics::calculateSalesVelocity(10, 0));
    }

    #[Test]
    public function calculate_days_of_stock_handles_zero_velocity_and_zero_available(): void
    {
        // Available > 0 and velocity > 0
        self::assertSame(20.0, InventoryMetrics::calculateDaysOfStock(40, 2.0));

        // Available <= 0
        self::assertSame(0.0, InventoryMetrics::calculateDaysOfStock(0, 2.0));
        self::assertSame(0.0, InventoryMetrics::calculateDaysOfStock(-5, 2.0));

        // Available > 0 but zero sales velocity -> infinite/null
        self::assertNull(InventoryMetrics::calculateDaysOfStock(50, 0.0));
    }

    #[Test]
    public function classify_stock_health_identifies_out_of_stock(): void
    {
        $status = InventoryMetrics::classifyStockHealth(
            availableQuantity: 0,
            dailyVelocity: 1.0,
            daysOfStock: 0.0,
            safetyStock: 10,
            reorderPoint: 20
        );

        self::assertSame(StockHealthStatus::OUT_OF_STOCK, $status);
        self::assertSame('out_of_stock', $status->value);
        self::assertSame('Дефицит', $status->label());
    }

    #[Test]
    public function classify_stock_health_identifies_critical_when_dos_low_or_below_safety_stock(): void
    {
        // DOS <= 7 days
        $status1 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 10,
            dailyVelocity: 2.0,
            daysOfStock: 5.0,
            safetyStock: 5,
            reorderPoint: 10
        );
        self::assertSame(StockHealthStatus::CRITICAL, $status1);
        self::assertSame('Критический', $status1->label());

        // Available <= safetyStock
        $status2 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 15,
            dailyVelocity: 1.0,
            daysOfStock: 15.0,
            safetyStock: 20,
            reorderPoint: 40
        );
        self::assertSame(StockHealthStatus::CRITICAL, $status2);
    }

    #[Test]
    public function classify_stock_health_identifies_overstock(): void
    {
        // DOS > 60 days
        $status1 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 150,
            dailyVelocity: 1.0,
            daysOfStock: 150.0,
            safetyStock: 20,
            reorderPoint: 40
        );
        self::assertSame(StockHealthStatus::OVERSTOCK, $status1);
        self::assertSame('Избыток', $status1->label());

        // Available > safetyStock * 3 with zero sales velocity
        $status2 = InventoryMetrics::classifyStockHealth(
            availableQuantity: 100,
            dailyVelocity: 0.0,
            daysOfStock: null,
            safetyStock: 20,
            reorderPoint: 40
        );
        self::assertSame(StockHealthStatus::OVERSTOCK, $status2);
    }

    #[Test]
    public function classify_stock_health_identifies_optimal_stock(): void
    {
        $status = InventoryMetrics::classifyStockHealth(
            availableQuantity: 50,
            dailyVelocity: 2.0,
            daysOfStock: 25.0,
            safetyStock: 15,
            reorderPoint: 30
        );
        self::assertSame(StockHealthStatus::OPTIMAL, $status);
        self::assertSame('В норме', $status->label());
    }

    #[Test]
    public function calculate_share_computes_ratio(): void
    {
        self::assertSame(0.25, InventoryMetrics::calculateShare(25, 100));
        self::assertSame(0.0, InventoryMetrics::calculateShare(25, 0));
    }
}
