<?php

namespace Database\Seeders\Demo;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

final class DemoDatasetGenerator
{
    private int $state;

    public function __construct(private readonly int $seed = 42)
    {
        $this->state = $this->seed;
    }

    /** Deterministic Linear Congruential Generator returning float [0.0, 1.0) */
    private function randomFloat(): float
    {
        $this->state = (1103515245 * $this->state + 12345) & 0x7FFFFFFF;

        return $this->state / 2147483648.0;
    }

    private function randomInt(int $min, int $max): int
    {
        return $min + (int) floor($this->randomFloat() * ($max - $min + 1));
    }

    /**
     * @template T
     * @param list<T> $array
     * @return T
     */
    private function randomChoice(array $array): mixed
    {
        return $array[$this->randomInt(0, count($array) - 1)];
    }

    /** @return list<array<string, mixed>> */
    public function generateDates(string $startDate, string $endDate): array
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $dates = [];

        foreach ($period as $dt) {
            /** @var Carbon $dt */
            $month = $dt->month;
            $season = match (true) {
                in_array($month, [12, 1, 2], true) => 'winter',
                in_array($month, [3, 4, 5], true) => 'spring',
                in_array($month, [6, 7, 8], true) => 'summer',
                default => 'autumn',
            };

            $dates[] = [
                'date' => $dt->format('Y-m-d'),
                'year' => $dt->year,
                'quarter' => $dt->quarter,
                'month' => $month,
                'month_name' => $dt->format('F'),
                'week' => (int) $dt->format('W'),
                'day' => $dt->day,
                'day_of_week' => (int) $dt->format('N'),
                'day_name' => $dt->format('l'),
                'is_weekend' => $dt->isWeekend(),
                'season' => $season,
            ];
        }

        return $dates;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function generateWorkspaceDimensions(string $workspaceId): array
    {
        $now = '2026-09-22 00:00:00';

        $categories = array_map(fn ($c) => array_merge($c, [
            'workspace_id' => $workspaceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), DemoDataCatalog::categories());

        $brands = array_map(fn ($b) => array_merge($b, [
            'workspace_id' => $workspaceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), DemoDataCatalog::brands());

        $regions = array_map(fn ($r) => array_merge($r, [
            'workspace_id' => $workspaceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), DemoDataCatalog::regions());

        $warehouses = array_map(fn ($w) => array_merge($w, [
            'workspace_id' => $workspaceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), DemoDataCatalog::warehouses());

        $channels = array_map(fn ($ch) => array_merge($ch, [
            'workspace_id' => $workspaceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), DemoDataCatalog::salesChannels());

        $suppliers = array_map(fn ($s) => array_merge($s, [
            'workspace_id' => $workspaceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), DemoDataCatalog::suppliers());

        $products = array_map(fn ($p) => [
            'id' => $p['id'],
            'workspace_id' => $workspaceId,
            'category_id' => $p['category_id'],
            'brand_id' => $p['brand_id'],
            'sku' => $p['sku'],
            'name' => $p['name'],
            'cost_price' => $p['cost_price'],
            'unit_price' => $p['unit_price'],
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ], DemoDataCatalog::products());

        return [
            'categories' => $categories,
            'brands' => $brands,
            'regions' => $regions,
            'warehouses' => $warehouses,
            'sales_channels' => $channels,
            'suppliers' => $suppliers,
            'products' => $products,
        ];
    }

    /**
     * @return array{orders: list<array<string, mixed>>, items: list<array<string, mixed>>}
     */
    public function generateOrdersAndItems(string $workspaceId, string $startDate, string $endDate): array
    {
        $products = DemoDataCatalog::products();
        $regions = DemoDataCatalog::regions();
        $channels = DemoDataCatalog::salesChannels();
        $warehouses = DemoDataCatalog::warehouses();

        $period = CarbonPeriod::create($startDate, $endDate);
        $orders = [];
        $items = [];
        $orderSeq = 1;
        $itemSeq = 1;

        $startDateTs = strtotime($startDate);
        $totalSpanDays = max(1, (int) round((strtotime($endDate) - $startDateTs) / 86400));

        foreach ($period as $dt) {
            /** @var Carbon $dt */
            $dateStr = $dt->format('Y-m-d');
            $month = $dt->month;

            // Trend: 15% annual growth
            $daysSinceStart = (int) round(($dt->timestamp - $startDateTs) / 86400);
            $trendMultiplier = 1.0 + (0.15 * ($daysSinceStart / $totalSpanDays));

            // Base orders per day: 8-15
            $baseOrderCount = (int) round($this->randomInt(8, 15) * $trendMultiplier);

            for ($o = 0; $o < $baseOrderCount; $o++) {
                $orderId = sprintf('ord-%s-%06d', $workspaceId, $orderSeq);
                $orderNumber = sprintf('AUTO-%04d-%06d', $dt->year, $orderSeq);
                $orderSeq++;

                $channel = $this->randomChoice($channels);
                $region = $this->randomChoice($regions);
                $status = $this->randomFloat() < 0.05 ? 'cancelled' : 'completed';

                // 1 to 3 distinct items per order
                $itemCount = $this->randomInt(1, 3);
                $orderTotal = 0.0;

                for ($i = 0; $i < $itemCount; $i++) {
                    $product = $this->selectProductBySeasonality($products, $month, $region['code']);
                    $warehouse = $this->randomChoice($warehouses);

                    // Quantity: 1-4 for retail, occasionally higher for wholesale
                    $qty = $this->randomInt(1, 4);
                    $unitPrice = (float) $product['unit_price'];
                    $unitCost = (float) $product['cost_price'];
                    $totalPrice = round($unitPrice * $qty, 2);
                    $totalCost = round($unitCost * $qty, 2);
                    $grossProfit = round($totalPrice - $totalCost, 2);

                    $orderTotal += $totalPrice;

                    $items[] = [
                        'id' => sprintf('item-%s-%07d', $workspaceId, $itemSeq++),
                        'workspace_id' => $workspaceId,
                        'order_id' => $orderId,
                        'product_id' => $product['id'],
                        'warehouse_id' => $warehouse['id'],
                        'category_id' => $product['category_id'],
                        'brand_id' => $product['brand_id'],
                        'region_id' => $region['id'],
                        'channel_id' => $channel['id'],
                        'order_date' => $dateStr,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'total_price' => $totalPrice,
                        'total_cost' => $totalCost,
                        'gross_profit' => $grossProfit,
                        'created_at' => $dateStr.' 12:00:00',
                        'updated_at' => $dateStr.' 12:00:00',
                    ];
                }

                $orders[] = [
                    'id' => $orderId,
                    'workspace_id' => $workspaceId,
                    'order_number' => $orderNumber,
                    'channel_id' => $channel['id'],
                    'region_id' => $region['id'],
                    'status' => $status,
                    'ordered_at' => $dateStr.' 12:00:00',
                    'order_date' => $dateStr,
                    'total_amount' => round($orderTotal, 2),
                    'currency' => 'RUB',
                    'created_at' => $dateStr.' 12:00:00',
                    'updated_at' => $dateStr.' 12:00:00',
                ];
            }
        }

        return ['orders' => $orders, 'items' => $items];
    }

    /**
     * @param list<array{id: string, category_id: string, brand_id: string, sku: string, name: string, cost_price: float, unit_price: float, seasonal_type: string, abc_class: string}> $products
     * @return array{id: string, category_id: string, brand_id: string, sku: string, name: string, cost_price: float, unit_price: float, seasonal_type: string, abc_class: string}
     */
    private function selectProductBySeasonality(array $products, int $month, string $regionCode): array
    {
        // Weighted selection
        $weights = [];
        foreach ($products as $idx => $prod) {
            $baseWeight = match ($prod['abc_class']) {
                'A' => 10.0,
                'B' => 4.0,
                default => 1.5,
            };

            $seasonalMultiplier = 1.0;
            if ($prod['seasonal_type'] === 'winter_seasonal') {
                if (in_array($month, [10, 11, 12, 1], true)) {
                    $seasonalMultiplier = 4.5;
                } elseif (in_array($month, [5, 6, 7], true)) {
                    $seasonalMultiplier = 0.1;
                }
                // Siberia & Ural peak earlier (September)
                if (in_array($regionCode, ['NSK', 'EKB'], true) && $month === 9) {
                    $seasonalMultiplier = 3.0;
                }
            } elseif ($prod['seasonal_type'] === 'summer_seasonal') {
                if (in_array($month, [4, 5, 6], true)) {
                    $seasonalMultiplier = 4.0;
                } elseif (in_array($month, [11, 12, 1], true)) {
                    $seasonalMultiplier = 0.1;
                }
            }

            $weights[$idx] = $baseWeight * $seasonalMultiplier;
        }

        $totalWeight = array_sum($weights);
        $rnd = $this->randomFloat() * $totalWeight;
        $cursor = 0.0;

        foreach ($weights as $idx => $w) {
            $cursor += $w;
            if ($rnd <= $cursor) {
                return $products[$idx];
            }
        }

        return $products[0];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function generateInventoryDaily(string $workspaceId, string $startDate, string $endDate, array $items): array
    {
        $products = DemoDataCatalog::products();
        $warehouses = DemoDataCatalog::warehouses();
        $period = CarbonPeriod::create($startDate, $endDate);
        $snapshots = [];
        $seq = 1;

        // Precompute daily consumption per product per warehouse
        $consumption = [];
        foreach ($items as $item) {
            $key = sprintf('%s_%s_%s', $item['order_date'], $item['product_id'], $item['warehouse_id']);
            $consumption[$key] = ($consumption[$key] ?? 0) + (int) $item['quantity'];
        }

        foreach ($products as $prod) {
            foreach ($warehouses as $wh) {
                // Determine base stock profile: stockout candidate, overstock candidate, or normal
                $isStockoutCandidate = ($prod['id'] === 'prod-conti-wint-16' && $wh['code'] === 'WH-MSK-01');
                $isOverstockCandidate = ($prod['seasonal_type'] === 'summer_seasonal' && $wh['code'] === 'WH-EKB-01');

                $safetyStock = match ($prod['abc_class']) {
                    'A' => 30,
                    'B' => 15,
                    default => 5,
                };
                $reorderPoint = $safetyStock * 2;
                $unitCost = (float) $prod['cost_price'];

                // Starting stock
                $currentStock = $isOverstockCandidate ? 180 : ($isStockoutCandidate ? 45 : 70);

                foreach ($period as $dt) {
                    /** @var Carbon $dt */
                    $dateStr = $dt->format('Y-m-d');
                    $month = $dt->month;

                    $key = sprintf('%s_%s_%s', $dateStr, $prod['id'], $wh['id']);
                    $consumed = $consumption[$key] ?? 0;

                    // In peak November, simulate stockout for the candidate product
                    if ($isStockoutCandidate && $month === 11 && $dt->day >= 12 && $dt->day <= 20) {
                        $currentStock = 0;
                    } else {
                        $currentStock = max(0, $currentStock - $consumed);
                        // Replenish if stock drops below reorder point (except during intentional stockout)
                        if ($currentStock < $reorderPoint) {
                            $currentStock += ($isOverstockCandidate ? 100 : 50);
                        }
                    }

                    $reserved = (int) round($currentStock * 0.1);
                    $available = max(0, $currentStock - $reserved);
                    $invValue = round($currentStock * $unitCost, 2);

                    $snapshots[] = [
                        'id' => sprintf('inv-%s-%07d', $workspaceId, $seq++),
                        'workspace_id' => $workspaceId,
                        'snapshot_date' => $dateStr,
                        'product_id' => $prod['id'],
                        'warehouse_id' => $wh['id'],
                        'quantity_on_hand' => $currentStock,
                        'quantity_reserved' => $reserved,
                        'quantity_available' => $available,
                        'safety_stock' => $safetyStock,
                        'reorder_point' => $reorderPoint,
                        'unit_cost' => $unitCost,
                        'inventory_value' => $invValue,
                        'created_at' => $dateStr.' 23:59:59',
                        'updated_at' => $dateStr.' 23:59:59',
                    ];
                }
            }
        }

        return $snapshots;
    }

    /** @return list<array<string, mixed>> */
    public function generateSupplierDeliveries(string $workspaceId, string $startDate, string $endDate): array
    {
        $suppliers = DemoDataCatalog::suppliers();
        $products = DemoDataCatalog::products();
        $warehouses = DemoDataCatalog::warehouses();
        $period = CarbonPeriod::create($startDate, $endDate);
        $deliveries = [];
        $seq = 1;

        foreach ($period as $dt) {
            /** @var Carbon $dt */
            // Every 5-7 days create purchase orders
            if ($dt->day % 6 !== 0) {
                continue;
            }

            $dateStr = $dt->format('Y-m-d');
            $supplier = $this->randomChoice($suppliers);
            $product = $this->randomChoice($products);
            $warehouse = $this->randomChoice($warehouses);

            $leadDays = (int) $supplier['lead_time_days'];
            $expectedDate = $dt->copy()->addDays($leadDays)->format('Y-m-d');

            $isDelayed = $this->randomFloat() > (float) $supplier['reliability_score'];
            $delayDays = $isDelayed ? $this->randomInt(3, 10) : 0;
            $actualDate = $dt->copy()->addDays($leadDays + $delayDays)->format('Y-m-d');

            $orderedQty = $this->randomInt(20, 100);
            $receivedQty = $isDelayed && $this->randomFloat() < 0.2 ? (int) round($orderedQty * 0.8) : $orderedQty;
            $status = match (true) {
                $receivedQty < $orderedQty => 'partial',
                $isDelayed => 'delayed',
                default => 'on_time',
            };

            $unitCost = (float) $product['cost_price'];
            $totalCost = round($unitCost * $receivedQty, 2);

            $deliveries[] = [
                'id' => sprintf('del-%s-%06d', $workspaceId, $seq++),
                'workspace_id' => $workspaceId,
                'supplier_id' => $supplier['id'],
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'order_date' => $dateStr,
                'expected_delivery_date' => $expectedDate,
                'actual_delivery_date' => $actualDate,
                'ordered_quantity' => $orderedQty,
                'received_quantity' => $receivedQty,
                'unit_purchase_cost' => $unitCost,
                'total_purchase_cost' => $totalCost,
                'delivery_status' => $status,
                'lead_time_days' => $leadDays,
                'delay_days' => $delayDays,
                'created_at' => $dateStr.' 08:00:00',
                'updated_at' => $actualDate.' 18:00:00',
            ];
        }

        return $deliveries;
    }
}
