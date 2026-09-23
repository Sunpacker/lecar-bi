<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use Database\Seeders\Performance\PerformanceDatasetGenerator;
use Database\Seeders\Performance\PerformanceDatasetProfile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PerformanceDatasetGeneratorTest extends TestCase
{
    #[Test]
    public function profiles_have_correct_target_specifications(): void
    {
        $small = PerformanceDatasetProfile::Small;
        self::assertSame('small', $small->value);
        self::assertSame(500, $small->ordersCount());
        self::assertSame(1_500, $small->itemsCount());
        self::assertSame(2_500, $small->inventorySnapshotsCount());
        self::assertSame(1_000, $small->deliveriesCount());
        self::assertSame(200, $small->productsCount());
        self::assertSame(10, $small->categoriesCount());
        self::assertSame(5, $small->warehousesCount());
        self::assertSame(50, $small->suppliersCount());
        self::assertSame(8, $small->regionsCount());

        $large = PerformanceDatasetProfile::Large;
        self::assertSame('large', $large->value);
        self::assertSame(100_000, $large->ordersCount());
        self::assertSame(300_000, $large->itemsCount());
        self::assertSame(500_000, $large->inventorySnapshotsCount());
        self::assertSame(200_000, $large->deliveriesCount());
        self::assertSame(10_000, $large->productsCount());

        self::assertSame(PerformanceDatasetProfile::Small, PerformanceDatasetProfile::fromName('small'));
        self::assertSame(PerformanceDatasetProfile::Large, PerformanceDatasetProfile::fromName('LARGE'));
    }

    #[Test]
    public function profile_throws_on_unknown_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PerformanceDatasetProfile::fromName('huge');
    }

    #[Test]
    public function dimension_id_scopes_correctly(): void
    {
        self::assertSame('cat-electronics-perf-ws-1', PerformanceDatasetGenerator::dimensionId('cat-electronics', 'perf-ws-1'));
        self::assertSame('cat-electronics-perf-ws-1', PerformanceDatasetGenerator::dimensionId('cat-electronics-perf-ws-1', 'perf-ws-1'));
    }

    #[Test]
    public function generator_is_strictly_deterministic_with_same_seed(): void
    {
        $gen1 = new PerformanceDatasetGenerator(seed: 42);
        $gen2 = new PerformanceDatasetGenerator(seed: 42);

        $dates1 = $gen1->generateDates('2026-01-01', '2026-12-31');
        $dates2 = $gen2->generateDates('2026-01-01', '2026-12-31');
        self::assertSame(365, count($dates1));
        self::assertSame($dates1, $dates2);

        $dims1 = $gen1->generateWorkspaceDimensions('perf-ws-1');
        $dims2 = $gen2->generateWorkspaceDimensions('perf-ws-1');
        self::assertSame($dims1, $dims2);
        self::assertCount(10, $dims1['categories']);
        self::assertCount(10, $dims1['brands']);
        self::assertCount(8, $dims1['regions']);
        self::assertCount(5, $dims1['warehouses']);
        self::assertCount(4, $dims1['sales_channels']);
        self::assertCount(50, $dims1['suppliers']);

        // Orders chunk comparison
        $ordersGen1 = iterator_to_array($gen1->generateOrderChunks('perf-ws-1', 50, 150, '2026-01-01', '2026-03-31', 25));
        $ordersGen2 = iterator_to_array($gen2->generateOrderChunks('perf-ws-1', 50, 150, '2026-01-01', '2026-03-31', 25));
        self::assertSame($ordersGen1, $ordersGen2);
    }

    #[Test]
    public function generator_produces_exact_row_counts_and_valid_math(): void
    {
        $gen = new PerformanceDatasetGenerator(seed: 42);
        $workspaceId = 'perf-ws-test';

        $ordersCount = 60;
        $itemsCount = 180;

        $totalOrders = 0;
        $totalItems = 0;

        foreach ($gen->generateOrderChunks($workspaceId, $ordersCount, $itemsCount, '2026-01-01', '2026-12-31', 20) as $chunk) {
            $totalOrders += count($chunk['orders']);
            $totalItems += count($chunk['items']);

            // Validate math
            $itemsByOrder = [];
            foreach ($chunk['items'] as $item) {
                $itemsByOrder[$item['order_id']][] = $item;
                self::assertSame($workspaceId, $item['workspace_id']);
                self::assertEqualsWithDelta(
                    round($item['total_price'] - $item['total_cost'], 2),
                    $item['gross_profit'],
                    0.01,
                    'Gross profit must match total_price - total_cost'
                );
            }

            foreach ($chunk['orders'] as $order) {
                self::assertSame($workspaceId, $order['workspace_id']);
                $orderItems = $itemsByOrder[$order['id']] ?? [];
                $calculatedTotal = round(array_sum(array_column($orderItems, 'total_price')), 2);
                self::assertEqualsWithDelta(
                    $calculatedTotal,
                    $order['total_amount'],
                    0.01,
                    'Order total amount must equal sum of item total prices'
                );
            }
        }

        self::assertSame($ordersCount, $totalOrders, 'Generated orders count must match requested count exactly.');
        self::assertSame($itemsCount, $totalItems, 'Generated items count must match requested count exactly.');
    }

    #[Test]
    public function inventory_daily_chunks_produce_exact_snapshot_count_without_duplicates(): void
    {
        $gen = new PerformanceDatasetGenerator(seed: 42);
        $workspaceId = 'perf-ws-test';
        $targetCount = 250;
        $productsCount = 50;

        $totalSnapshots = 0;
        $seenKeys = [];

        foreach ($gen->generateInventoryDailyChunks($workspaceId, $targetCount, $productsCount, 50) as $chunk) {
            $totalSnapshots += count($chunk);
            foreach ($chunk as $snap) {
                self::assertSame($workspaceId, $snap['workspace_id']);
                self::assertGreaterThanOrEqual(0, $snap['quantity_on_hand']);
                self::assertGreaterThanOrEqual(0, $snap['quantity_available']);
                self::assertGreaterThanOrEqual(0, $snap['quantity_reserved']);

                // Verify composite uniqueness constraint
                $key = "{$snap['snapshot_date']}_{$snap['product_id']}_{$snap['warehouse_id']}";
                self::assertArrayNotHasKey($key, $seenKeys, "Duplicate inventory snapshot detected: {$key}");
                $seenKeys[$key] = true;
            }
        }

        self::assertSame($targetCount, $totalSnapshots, 'Total inventory snapshots must match target.');
    }

    #[Test]
    public function supplier_deliveries_produce_exact_count_and_consistent_dates(): void
    {
        $gen = new PerformanceDatasetGenerator(seed: 42);
        $workspaceId = 'perf-ws-test';
        $targetCount = 100;
        $productsCount = 30;

        $totalDeliveries = 0;

        foreach ($gen->generateSupplierDeliveryChunks($workspaceId, $targetCount, $productsCount, '2026-01-01', '2026-12-31', 30) as $chunk) {
            $totalDeliveries += count($chunk);
            foreach ($chunk as $del) {
                self::assertSame($workspaceId, $del['workspace_id']);
                self::assertGreaterThan(0, $del['ordered_quantity']);
                self::assertGreaterThan(0, $del['received_quantity']);
                self::assertContains($del['delivery_status'], ['on_time', 'delayed', 'partial']);

                $orderDate = $del['order_date'];
                $expected = $del['expected_delivery_date'];
                $actual = $del['actual_delivery_date'];

                self::assertGreaterThanOrEqual($orderDate, $expected);
                self::assertGreaterThanOrEqual($expected, $actual);
            }
        }

        self::assertSame($targetCount, $totalDeliveries, 'Total deliveries must match target.');
    }
}
