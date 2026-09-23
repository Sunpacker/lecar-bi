<?php

declare(strict_types=1);

namespace Database\Seeders\Performance;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Generator;

final class PerformanceDatasetGenerator
{
    private int $state;

    public function __construct(private readonly int $seed = 42)
    {
        $this->state = $this->seed;
    }

    public static function dimensionId(string $baseId, string $workspaceId): string
    {
        return str_ends_with($baseId, "-{$workspaceId}") ? $baseId : "{$baseId}-{$workspaceId}";
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
     *
     * @param  list<T>  $array
     * @return T
     */
    private function randomChoice(array $array): mixed
    {
        return $array[$this->randomInt(0, count($array) - 1)];
    }

    /**
     * @return list<array<string, mixed>>
     */
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

    /**
     * @return array{
     *     categories: list<array<string, mixed>>,
     *     brands: list<array<string, mixed>>,
     *     regions: list<array<string, mixed>>,
     *     warehouses: list<array<string, mixed>>,
     *     sales_channels: list<array<string, mixed>>,
     *     suppliers: list<array<string, mixed>>
     * }
     */
    public function generateWorkspaceDimensions(string $workspaceId): array
    {
        $now = '2026-09-23 00:00:00';

        $rawCategories = [
            ['id' => 'cat-electronics', 'name' => 'Электроника и датчики', 'slug' => 'electronics', 'code' => 'ELEC'],
            ['id' => 'cat-auto-parts', 'name' => 'Автозапчасти и компоненты', 'slug' => 'auto-parts', 'code' => 'PARTS'],
            ['id' => 'cat-tires-wheels', 'name' => 'Шины и диски', 'slug' => 'tires-and-wheels', 'code' => 'TIRES'],
            ['id' => 'cat-brakes', 'name' => 'Тормозная система', 'slug' => 'braking-systems', 'code' => 'BRAKES'],
            ['id' => 'cat-oils-fluids', 'name' => 'Масла и автохимия', 'slug' => 'oils-and-fluids', 'code' => 'FLUIDS'],
            ['id' => 'cat-filters', 'name' => 'Фильтры', 'slug' => 'filters', 'code' => 'FILTERS'],
            ['id' => 'cat-suspension', 'name' => 'Подвеска и рулевое управление', 'slug' => 'suspension', 'code' => 'SUSP'],
            ['id' => 'cat-electrical', 'name' => 'Электрика и освещение', 'slug' => 'electrical', 'code' => 'LIGHT'],
            ['id' => 'cat-cooling', 'name' => 'Охлаждение и климат', 'slug' => 'cooling', 'code' => 'COOL'],
            ['id' => 'cat-transmission', 'name' => 'Трансмиссия и сцепление', 'slug' => 'transmission', 'code' => 'TRANS'],
        ];

        $categories = array_map(fn ($c) => [
            'id' => self::dimensionId($c['id'], $workspaceId),
            'workspace_id' => $workspaceId,
            'name' => $c['name'],
            'slug' => $c['slug'],
            'code' => $c['code'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rawCategories);

        $rawBrands = [
            ['id' => 'br-bosch', 'name' => 'Bosch', 'country' => 'Германия'],
            ['id' => 'br-valeo', 'name' => 'Valeo', 'country' => 'Франция'],
            ['id' => 'br-brembo', 'name' => 'Brembo', 'country' => 'Италия'],
            ['id' => 'br-continental', 'name' => 'Continental', 'country' => 'Германия'],
            ['id' => 'br-michelin', 'name' => 'Michelin', 'country' => 'Франция'],
            ['id' => 'br-mann', 'name' => 'Mann-Filter', 'country' => 'Германия'],
            ['id' => 'br-castrol', 'name' => 'Castrol', 'country' => 'Великобритания'],
            ['id' => 'br-lukoil', 'name' => 'Lukoil', 'country' => 'Россия'],
            ['id' => 'br-osram', 'name' => 'Osram', 'country' => 'Германия'],
            ['id' => 'br-febi', 'name' => 'Febi Bilstein', 'country' => 'Германия'],
        ];

        $brands = array_map(fn ($b) => [
            'id' => self::dimensionId($b['id'], $workspaceId),
            'workspace_id' => $workspaceId,
            'name' => $b['name'],
            'country' => $b['country'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rawBrands);

        $rawRegions = [
            ['id' => 'reg-north', 'name' => 'Северный регион (СПб)', 'code' => 'SPB'],
            ['id' => 'reg-central', 'name' => 'Центральный регион (Москва)', 'code' => 'MSK'],
            ['id' => 'reg-south', 'name' => 'Южный регион (Краснодар)', 'code' => 'KRD'],
            ['id' => 'reg-west', 'name' => 'Западный регион (Калининград)', 'code' => 'KGD'],
            ['id' => 'reg-east', 'name' => 'Восточный регион (Владивосток)', 'code' => 'VVO'],
            ['id' => 'reg-ural', 'name' => 'Уральский регион (Екатеринбург)', 'code' => 'EKB'],
            ['id' => 'reg-siberia', 'name' => 'Сибирский регион (Новосибирск)', 'code' => 'NSK'],
            ['id' => 'reg-volga', 'name' => 'Приволжский регион (Самара)', 'code' => 'SAM'],
        ];

        $regions = array_map(fn ($r) => [
            'id' => self::dimensionId($r['id'], $workspaceId),
            'workspace_id' => $workspaceId,
            'name' => $r['name'],
            'code' => $r['code'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rawRegions);

        $rawWarehouses = [
            ['id' => 'wh-central', 'region_id' => 'reg-central', 'name' => 'Центральный склад Москва', 'code' => 'WH-MSK'],
            ['id' => 'wh-north', 'region_id' => 'reg-north', 'name' => 'Северный склад СПб', 'code' => 'WH-SPB'],
            ['id' => 'wh-south', 'region_id' => 'reg-south', 'name' => 'Южный склад Краснодар', 'code' => 'WH-KRD'],
            ['id' => 'wh-east', 'region_id' => 'reg-east', 'name' => 'Восточный склад Владивосток', 'code' => 'WH-VVO'],
            ['id' => 'wh-west', 'region_id' => 'reg-west', 'name' => 'Западный склад Калининград', 'code' => 'WH-KGD'],
        ];

        $warehouses = array_map(fn ($w) => [
            'id' => self::dimensionId($w['id'], $workspaceId),
            'workspace_id' => $workspaceId,
            'region_id' => self::dimensionId($w['region_id'], $workspaceId),
            'name' => $w['name'],
            'code' => $w['code'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rawWarehouses);

        $rawSalesChannels = [
            ['id' => 'ch-b2b-portal', 'name' => 'Оптовый портал B2B', 'code' => 'B2B_PORTAL'],
            ['id' => 'ch-b2c-web', 'name' => 'Интернет-магазин B2C', 'code' => 'B2C_WEB'],
            ['id' => 'ch-retail-store', 'name' => 'Сеть розничных автомагазинов', 'code' => 'RETAIL_STORE'],
            ['id' => 'ch-marketplace', 'name' => 'Маркетплейс', 'code' => 'MARKETPLACE'],
        ];

        $salesChannels = array_map(fn ($sc) => [
            'id' => self::dimensionId($sc['id'], $workspaceId),
            'workspace_id' => $workspaceId,
            'name' => $sc['name'],
            'code' => $sc['code'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rawSalesChannels);

        // 50 Suppliers: key named suppliers + auto suppliers for search 'Auto'
        $rawSuppliers = [
            ['id' => 'sup-bosch', 'name' => 'Bosch Automotive Supplies', 'lead_time_days' => 5, 'reliability_score' => 0.95],
            ['id' => 'sup-valeo', 'name' => 'Valeo Distribution Russia', 'lead_time_days' => 7, 'reliability_score' => 0.90],
            ['id' => 'sup-brembo', 'name' => 'Brembo SpA Logistics', 'lead_time_days' => 10, 'reliability_score' => 0.92],
            ['id' => 'sup-mann', 'name' => 'Mann+Hummel Filter Supplies', 'lead_time_days' => 6, 'reliability_score' => 0.96],
            ['id' => 'sup-continental', 'name' => 'Continental Auto Parts', 'lead_time_days' => 8, 'reliability_score' => 0.94],
        ];

        for ($s = 6; $s <= 50; $s++) {
            $rawSuppliers[] = [
                'id' => sprintf('sup-auto-%02d', $s),
                'name' => sprintf('Auto Supplier %02d Direct', $s),
                'lead_time_days' => 4 + ($s % 11),
                'reliability_score' => round(0.80 + (($s % 19) * 0.01), 2),
            ];
        }

        $suppliers = array_map(fn ($s) => [
            'id' => self::dimensionId($s['id'], $workspaceId),
            'workspace_id' => $workspaceId,
            'name' => $s['name'],
            'lead_time_days' => $s['lead_time_days'],
            'reliability_score' => $s['reliability_score'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rawSuppliers);

        return [
            'categories' => $categories,
            'brands' => $brands,
            'regions' => $regions,
            'warehouses' => $warehouses,
            'sales_channels' => $salesChannels,
            'suppliers' => $suppliers,
        ];
    }

    /**
     * Generates product metadata catalog in memory for order/inventory selection.
     *
     * @return list<array{
     *     id: string,
     *     category_id: string,
     *     brand_id: string,
     *     sku: string,
     *     name: string,
     *     cost_price: float,
     *     unit_price: float,
     *     status: string,
     *     abc_class: string,
     *     seasonal_type: string
     * }>
     */
    public function generateProductsCatalog(string $workspaceId, int $count): array
    {
        $dims = $this->generateWorkspaceDimensions($workspaceId);
        $categories = $dims['categories'];
        $brands = $dims['brands'];

        $products = [];

        for ($p = 1; $p <= $count; $p++) {
            $cat = $categories[($p - 1) % count($categories)];
            $brand = $brands[($p - 1) % count($brands)];

            $prodId = sprintf('prod-%s-%06d', $workspaceId, $p);
            $sku = sprintf('SKU-%s-%06d', $cat['code'], $p);

            // Ensure specific named products exist for search scenarios:
            // INV-09: category cat-electronics with 'Sensor', ABC class A
            // INV-05: category cat-filters with 'Filter'
            // INV-07: category cat-auto-parts
            $namePrefix = match (true) {
                $cat['slug'] === 'electronics' && $p % 2 === 1 => 'Sensor Pro Electronics',
                $cat['slug'] === 'electronics' => 'Smart Controller Sensor Module',
                $cat['slug'] === 'filters' => 'Oil Filter Premium High-Flow',
                $cat['slug'] === 'auto-parts' && $p % 3 === 0 => 'Auto Filter Replacement Kit',
                $cat['slug'] === 'auto-parts' => 'Auto Performance Component',
                $cat['slug'] === 'tires-and-wheels' => 'Tire Radial Performance',
                $cat['slug'] === 'braking-systems' => 'Brake Disc Carbon-Metallic',
                default => 'Automotive Spare Part',
            };

            $name = sprintf('%s #%05d (%s)', $namePrefix, $p, $brand['name']);

            // ABC distribution: ~20% A, ~30% B, ~50% C
            $abcClass = match (true) {
                $p <= (int) round($count * 0.20) => 'A',
                $p <= (int) round($count * 0.50) => 'B',
                default => 'C',
            };

            $costPrice = round(500.0 + (($p % 450) * 25.5), 2);
            $markup = match ($abcClass) {
                'A' => 1.45,
                'B' => 1.35,
                default => 1.25,
            };
            $unitPrice = round($costPrice * $markup, 2);

            $seasonalType = match ($p % 5) {
                0 => 'winter_seasonal',
                1 => 'summer_seasonal',
                default => 'regular',
            };

            $products[] = [
                'id' => $prodId,
                'category_id' => $cat['id'],
                'brand_id' => $brand['id'],
                'sku' => $sku,
                'name' => $name,
                'cost_price' => $costPrice,
                'unit_price' => $unitPrice,
                'status' => 'active',
                'abc_class' => $abcClass,
                'seasonal_type' => $seasonalType,
            ];
        }

        return $products;
    }

    /**
     * Yields product records in chunks for database insertion.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function generateProductChunks(string $workspaceId, int $count, int $chunkSize = 500): Generator
    {
        $now = '2026-09-23 00:00:00';
        $catalog = $this->generateProductsCatalog($workspaceId, $count);

        $chunk = [];
        foreach ($catalog as $prod) {
            $chunk[] = [
                'id' => $prod['id'],
                'workspace_id' => $workspaceId,
                'category_id' => $prod['category_id'],
                'brand_id' => $prod['brand_id'],
                'sku' => $prod['sku'],
                'name' => $prod['name'],
                'cost_price' => $prod['cost_price'],
                'unit_price' => $prod['unit_price'],
                'status' => $prod['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }

        if (count($chunk) > 0) {
            yield $chunk;
        }
    }

    /**
     * Generates orders and items in streaming chunks without accumulating full dataset in RAM.
     *
     * @return Generator<int, array{orders: list<array<string, mixed>>, items: list<array<string, mixed>>}>
     */
    public function generateOrderChunks(
        string $workspaceId,
        int $ordersCount,
        int $itemsCount,
        string $startDate = '2026-01-01',
        string $endDate = '2026-12-31',
        int $chunkSize = 500
    ): Generator {
        $products = $this->generateProductsCatalog($workspaceId, min(500, max(200, (int) round($ordersCount / 10))));
        $dims = $this->generateWorkspaceDimensions($workspaceId);
        $channels = $dims['sales_channels'];
        $regions = $dims['regions'];
        $warehouses = $dims['warehouses'];

        $startDateTs = strtotime($startDate);
        $endDateTs = strtotime($endDate);
        $totalDays = max(1, (int) round(($endDateTs - $startDateTs) / 86400));

        $ordersChunk = [];
        $itemsChunk = [];
        $itemSeq = 1;
        $itemsRemaining = $itemsCount;

        for ($o = 1; $o <= $ordersCount; $o++) {
            $ordersRemaining = $ordersCount - $o + 1;

            // Deterministic date distribution across the year
            $dayOffset = (int) floor((($o - 1) / (float) $ordersCount) * $totalDays);
            $orderDate = date('Y-m-d', $startDateTs + ($dayOffset * 86400));
            $orderTime = sprintf('%02d:%02d:%02d', 8 + ($o % 12), ($o * 7) % 60, ($o * 13) % 60);
            $orderedAt = "{$orderDate} {$orderTime}";

            $orderId = sprintf('ord-%s-%07d', $workspaceId, $o);
            $orderNumber = sprintf('ORD-2026-%07d', $o);

            $channel = $channels[($o - 1) % count($channels)];
            $region = $regions[($o - 1) % count($regions)];
            $status = ($o % 20 === 0) ? 'cancelled' : 'completed';

            // Items per order calculation: ensure total items equals exactly $itemsCount
            if ($ordersRemaining === 1) {
                $targetItems = $itemsRemaining;
            } else {
                $avgNeeded = $itemsRemaining / (float) $ordersRemaining;
                $targetItems = (int) round($avgNeeded);
                $targetItems = max(1, min(6, $targetItems));
                // Safety bound
                if ($itemsRemaining - $targetItems < ($ordersRemaining - 1)) {
                    $targetItems = 1;
                }
            }

            $targetItems = max(1, $targetItems);
            $itemsRemaining -= $targetItems;

            $orderTotal = 0.0;

            for ($i = 0; $i < $targetItems; $i++) {
                $product = $products[($itemSeq + ($i * 7)) % count($products)];
                $warehouse = $warehouses[($itemSeq + ($i * 3)) % count($warehouses)];

                $qty = 1 + (($itemSeq + $i) % 4);
                $unitPrice = $product['unit_price'];
                $unitCost = $product['cost_price'];
                $totalPrice = round($unitPrice * $qty, 2);
                $totalCost = round($unitCost * $qty, 2);
                $grossProfit = round($totalPrice - $totalCost, 2);

                $orderTotal += $totalPrice;

                $itemsChunk[] = [
                    'id' => sprintf('item-%s-%08d', $workspaceId, $itemSeq++),
                    'workspace_id' => $workspaceId,
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'warehouse_id' => $warehouse['id'],
                    'category_id' => $product['category_id'],
                    'brand_id' => $product['brand_id'],
                    'region_id' => $region['id'],
                    'channel_id' => $channel['id'],
                    'order_date' => $orderDate,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'total_price' => $totalPrice,
                    'total_cost' => $totalCost,
                    'gross_profit' => $grossProfit,
                    'created_at' => $orderedAt,
                    'updated_at' => $orderedAt,
                ];
            }

            $ordersChunk[] = [
                'id' => $orderId,
                'workspace_id' => $workspaceId,
                'order_number' => $orderNumber,
                'channel_id' => $channel['id'],
                'region_id' => $region['id'],
                'status' => $status,
                'ordered_at' => $orderedAt,
                'order_date' => $orderDate,
                'total_amount' => round($orderTotal, 2),
                'currency' => 'RUB',
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
            ];

            if (count($ordersChunk) >= $chunkSize) {
                yield [
                    'orders' => $ordersChunk,
                    'items' => $itemsChunk,
                ];
                $ordersChunk = [];
                $itemsChunk = [];
            }
        }

        if (count($ordersChunk) > 0) {
            yield [
                'orders' => $ordersChunk,
                'items' => $itemsChunk,
            ];
        }
    }

    /**
     * Generates daily inventory snapshots in chunks without memory exhaustion.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function generateInventoryDailyChunks(
        string $workspaceId,
        int $snapshotsCount,
        int $productsCount,
        int $chunkSize = 500
    ): Generator {
        $products = $this->generateProductsCatalog($workspaceId, $productsCount);
        $dims = $this->generateWorkspaceDimensions($workspaceId);
        $warehouses = $dims['warehouses'];

        // 10 representative snapshot dates across 2026, including 2026-05-15 (INV-02) and 2026-12-31 (latest)
        $snapshotDates = [
            '2026-01-15',
            '2026-02-15',
            '2026-03-15',
            '2026-04-15',
            '2026-05-15', // INV-02 selective
            '2026-06-15',
            '2026-07-15',
            '2026-08-15',
            '2026-09-15',
            '2026-12-31', // latest snapshot date
        ];

        $totalWarehouses = count($warehouses);
        $pairsPerDate = (int) ceil($snapshotsCount / (float) count($snapshotDates));

        $chunk = [];
        $seq = 1;
        $totalEmitted = 0;

        foreach ($snapshotDates as $dateStr) {
            $emittedOnDate = 0;

            for ($pIdx = 0; $pIdx < count($products); $pIdx++) {
                $product = $products[$pIdx];

                for ($wIdx = 0; $wIdx < $totalWarehouses; $wIdx++) {
                    if ($totalEmitted >= $snapshotsCount) {
                        break 3;
                    }
                    if ($emittedOnDate >= $pairsPerDate) {
                        break 2;
                    }

                    $warehouse = $warehouses[$wIdx];

                    // Stock health simulation
                    // Deterministic variation: healthy, critical/low_stock, overstock, out_of_stock
                    $variant = ($pIdx + $wIdx + $seq) % 20;
                    $unitCost = $product['cost_price'];
                    $safetyStock = ($product['abc_class'] === 'A') ? 30 : (($product['abc_class'] === 'B') ? 15 : 5);
                    $reorderPoint = $safetyStock * 2;

                    $onHand = match (true) {
                        $variant === 0 => 0, // out of stock
                        $variant <= 3 => max(1, (int) round($safetyStock * 0.5)), // low_stock / critical
                        $variant >= 18 => $safetyStock * 5, // overstock
                        default => $safetyStock * 2 + ($seq % 25), // optimal
                    };

                    $reserved = (int) round($onHand * 0.1);
                    $available = max(0, $onHand - $reserved);
                    $invValue = round($onHand * $unitCost, 2);

                    $chunk[] = [
                        'id' => sprintf('inv-%s-%07d', $workspaceId, $seq++),
                        'workspace_id' => $workspaceId,
                        'snapshot_date' => $dateStr,
                        'product_id' => $product['id'],
                        'warehouse_id' => $warehouse['id'],
                        'quantity_on_hand' => $onHand,
                        'quantity_reserved' => $reserved,
                        'quantity_available' => $available,
                        'safety_stock' => $safetyStock,
                        'reorder_point' => $reorderPoint,
                        'unit_cost' => $unitCost,
                        'inventory_value' => $invValue,
                        'created_at' => "{$dateStr} 23:59:59",
                        'updated_at' => "{$dateStr} 23:59:59",
                    ];

                    $emittedOnDate++;
                    $totalEmitted++;

                    if (count($chunk) >= $chunkSize) {
                        yield $chunk;
                        $chunk = [];
                    }
                }
            }
        }

        if (count($chunk) > 0) {
            yield $chunk;
        }
    }

    /**
     * Generates supplier deliveries in chunks without memory exhaustion.
     *
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function generateSupplierDeliveryChunks(
        string $workspaceId,
        int $deliveriesCount,
        int $productsCount,
        string $startDate = '2026-01-01',
        string $endDate = '2026-12-31',
        int $chunkSize = 500
    ): Generator {
        $products = $this->generateProductsCatalog($workspaceId, $productsCount);
        $dims = $this->generateWorkspaceDimensions($workspaceId);
        $suppliers = $dims['suppliers'];
        $warehouses = $dims['warehouses'];

        $startDateTs = strtotime($startDate);
        $endDateTs = strtotime($endDate);
        $totalDays = max(1, (int) round(($endDateTs - $startDateTs) / 86400));

        $chunk = [];

        for ($d = 1; $d <= $deliveriesCount; $d++) {
            $dayOffset = (int) floor((($d - 1) / (float) $deliveriesCount) * $totalDays);
            $orderDateTs = $startDateTs + ($dayOffset * 86400);
            $orderDate = date('Y-m-d', $orderDateTs);

            $supplier = $suppliers[($d - 1) % count($suppliers)];
            $product = $products[($d - 1) % count($products)];
            $warehouse = $warehouses[($d - 1) % count($warehouses)];

            $leadDays = (int) $supplier['lead_time_days'];
            $expectedDeliveryDate = date('Y-m-d', $orderDateTs + ($leadDays * 86400));

            // Status: 70% on_time, 20% delayed, 10% partial
            $mod = $d % 10;
            $status = match (true) {
                $mod <= 6 => 'on_time',
                $mod <= 8 => 'delayed',
                default => 'partial',
            };

            $delayDays = ($status === 'delayed') ? 2 + ($d % 8) : 0;
            $actualDeliveryDate = date('Y-m-d', $orderDateTs + (($leadDays + $delayDays) * 86400));

            $orderedQty = 20 + ($d % 80);
            $receivedQty = match ($status) {
                'partial' => (int) round($orderedQty * 0.75),
                default => $orderedQty,
            };

            $unitCost = $product['cost_price'];
            $totalCost = round($unitCost * $receivedQty, 2);

            $chunk[] = [
                'id' => sprintf('del-%s-%07d', $workspaceId, $d),
                'workspace_id' => $workspaceId,
                'supplier_id' => $supplier['id'],
                'product_id' => $product['id'],
                'warehouse_id' => $warehouse['id'],
                'order_date' => $orderDate,
                'expected_delivery_date' => $expectedDeliveryDate,
                'actual_delivery_date' => $actualDeliveryDate,
                'ordered_quantity' => $orderedQty,
                'received_quantity' => $receivedQty,
                'unit_purchase_cost' => $unitCost,
                'total_purchase_cost' => $totalCost,
                'delivery_status' => $status,
                'lead_time_days' => $leadDays,
                'delay_days' => $delayDays,
                'created_at' => "{$orderDate} 08:00:00",
                'updated_at' => "{$actualDeliveryDate} 18:00:00",
            ];

            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }

        if (count($chunk) > 0) {
            yield $chunk;
        }
    }
}
