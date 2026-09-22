<?php

namespace Tests\Unit\DemoData;

use Database\Seeders\Demo\DemoDatasetGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DemoDatasetGeneratorTest extends TestCase
{
    #[Test]
    public function generator_is_deterministic_and_exhibits_seasonality_and_inventory_extremes(): void
    {
        $gen1 = new DemoDatasetGenerator(seed: 42);
        $gen2 = new DemoDatasetGenerator(seed: 42);

        $dates1 = $gen1->generateDates('2025-01-01', '2025-12-31');
        $dates2 = $gen2->generateDates('2025-01-01', '2025-12-31');

        self::assertSame(365, count($dates1));
        self::assertSame($dates1, $dates2, 'Same seed must generate identical date dimension.');

        $sales1 = $gen1->generateOrdersAndItems('ws-1', '2025-01-01', '2025-12-31');
        $sales2 = $gen2->generateOrdersAndItems('ws-1', '2025-01-01', '2025-12-31');

        self::assertSame(count($sales1['orders']), count($sales2['orders']));
        self::assertSame(count($sales1['items']), count($sales2['items']));
        self::assertSame($sales1['orders'][0]['total_amount'], $sales2['orders'][0]['total_amount']);

        // Test Seasonality: Winter tires sales in Q4 (Oct-Dec) must significantly exceed Q2 (Apr-Jun)
        $winterQ4Quantity = 0;
        $winterQ2Quantity = 0;

        foreach ($sales1['items'] as $item) {
            self::assertSame('ws-1', $item['workspace_id']);
            self::assertEqualsWithDelta(
                (float) $item['total_price'] - (float) $item['total_cost'],
                (float) $item['gross_profit'],
                0.01,
                'Gross profit must equal total price minus total cost.'
            );

            $month = (int) substr((string) $item['order_date'], 5, 2);
            if ($item['product_id'] === 'prod-conti-wint-16') {
                if ($month >= 10 && $month <= 12) {
                    $winterQ4Quantity += (int) $item['quantity'];
                } elseif ($month >= 4 && $month <= 6) {
                    $winterQ2Quantity += (int) $item['quantity'];
                }
            }
        }

        self::assertGreaterThan(
            $winterQ2Quantity * 3,
            $winterQ4Quantity,
            'Winter tire volume in Q4 must be at least 3x greater than Q2.'
        );

        // Test Inventory Snapshots: stockouts and overstock must occur
        $inventory = $gen1->generateInventoryDaily('ws-1', '2025-10-01', '2025-12-31', $sales1['items']);
        $hasStockout = false;
        $hasOverstock = false;

        foreach ($inventory as $snap) {
            self::assertSame('ws-1', $snap['workspace_id']);
            if ($snap['quantity_available'] <= 0) {
                $hasStockout = true;
            }
            if ($snap['quantity_available'] > 4 * $snap['safety_stock']) {
                $hasOverstock = true;
            }
        }

        self::assertTrue($hasStockout, 'Inventory snapshots must contain stockout situations (quantity_available <= 0).');
        self::assertTrue($hasOverstock, 'Inventory snapshots must contain overstock situations.');

        // Test Supplier Deliveries: deliveries must include delayed and on-time shipments
        $deliveries = $gen1->generateSupplierDeliveries('ws-1', '2025-01-01', '2025-12-31');
        self::assertNotEmpty($deliveries);
        $hasDelayed = false;
        $hasOnTime = false;
        foreach ($deliveries as $delivery) {
            self::assertSame('ws-1', $delivery['workspace_id']);
            if ($delivery['delivery_status'] === 'delayed') {
                $hasDelayed = true;
            }
            if ($delivery['delivery_status'] === 'on_time') {
                $hasOnTime = true;
            }
        }
        self::assertTrue($hasDelayed, 'Supplier deliveries must contain delayed deliveries.');
        self::assertTrue($hasOnTime, 'Supplier deliveries must contain on-time deliveries.');
    }
}
