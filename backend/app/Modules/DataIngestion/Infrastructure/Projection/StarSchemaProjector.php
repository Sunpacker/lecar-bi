<?php

namespace App\Modules\DataIngestion\Infrastructure\Projection;

use App\Modules\DataIngestion\Application\Contracts\StarSchemaProjectorInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Idempotent Star Schema Projector.
 *
 * Projects validated sales and inventory rows into the analytics star schema.
 * All writes use updateOrInsert (upsert semantics) so re-running the same
 * data never creates duplicates.
 *
 * Dimension lookup strategy:
 *   - For dimensions with a synthetic UUID PK, we use `updateOrInsert` to
 *     ensure the row exists, then fetch the `id` via a `where` query.
 *   - dim_dates uses `date` as PK (natural key) — no UUID needed.
 *
 * Dependency: Illuminate\Database\ConnectionInterface is injected so the
 * class can be unit-tested with a mock (no Laravel service container needed).
 */
final class StarSchemaProjector implements StarSchemaProjectorInterface
{
    public function __construct(
        private readonly ConnectionInterface $db
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Project one validated sales row into the star schema.
     *
     * @param  array<string, string>  $row
     */
    public function projectSalesRow(string $workspaceId, array $row): void
    {
        // 1. Dimensions
        $dateId = $this->upsertDate($row['order_date']);
        $channelId = $this->upsertSalesChannel($workspaceId, $row['channel_code']);
        $regionId = $this->upsertRegion($workspaceId, $row['region_code']);
        $warehouseId = $this->upsertWarehouse($workspaceId, $row['warehouse_code'], $regionId);
        $productId = $this->upsertProduct($workspaceId, $row['sku']);

        // 2. fact_orders
        $orderId = $this->upsertOrder(
            workspaceId: $workspaceId,
            orderNumber: $row['order_number'],
            channelId: $channelId,
            regionId: $regionId,
            orderDate: $row['order_date'],
            orderStatus: $row['order_status'],
        );

        // 3. fact_order_items
        $this->upsertOrderItem(
            workspaceId: $workspaceId,
            orderId: $orderId,
            productId: $productId,
            warehouseId: $warehouseId,
            categoryId: null,
            brandId: null,
            regionId: $regionId,
            channelId: $channelId,
            orderDate: $row['order_date'],
            quantity: (int) $row['quantity'],
            unitPrice: (float) $row['unit_price'],
            unitCost: (float) $row['unit_cost'],
        );
    }

    /**
     * Project one validated inventory row into the star schema.
     *
     * @param  array<string, string>  $row
     */
    public function projectInventoryRow(string $workspaceId, array $row): void
    {
        // 1. Dimensions
        $dateId = $this->upsertDate($row['snapshot_date']);
        $warehouseId = $this->upsertWarehouse($workspaceId, $row['warehouse_code']);
        $productId = $this->upsertProduct($workspaceId, $row['sku']);

        // 2. fact_inventory_daily
        $this->upsertInventoryDaily(
            workspaceId: $workspaceId,
            snapshotDate: $row['snapshot_date'],
            productId: $productId,
            warehouseId: $warehouseId,
            quantityOnHand: (int) $row['quantity_on_hand'],
            quantityReserved: (int) $row['quantity_reserved'],
            safetyStock: (int) $row['safety_stock'],
            reorderPoint: (int) $row['reorder_point'],
            unitCost: (float) ($row['unit_cost'] ?? 0),
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dimension upserts
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Upsert dim_dates row; returns the date value (PK = natural date string).
     */
    private function upsertDate(string $dateValue): string
    {
        $date = new \DateTimeImmutable($dateValue);

        $this->db->table('dim_dates')->updateOrInsert(
            ['date' => $dateValue],
            [
                'date' => $dateValue,
                'year' => (int) $date->format('Y'),
                'quarter' => (int) ceil((int) $date->format('n') / 3),
                'month' => (int) $date->format('n'),
                'month_name' => $date->format('F'),
                'week' => (int) $date->format('W'),
                'day' => (int) $date->format('j'),
                'day_of_week' => (int) $date->format('N'),
                'day_name' => $date->format('l'),
                'is_weekend' => in_array((int) $date->format('N'), [6, 7], true),
                'season' => $this->resolveSeason((int) $date->format('n')),
            ]
        );

        return $dateValue;
    }

    /**
     * Upsert dim_sales_channels; returns dimension id.
     */
    private function upsertSalesChannel(string $workspaceId, string $code): string
    {
        $id = $this->findDimId('dim_sales_channels', $workspaceId, 'code', $code);

        if ($id === null) {
            $id = Str::uuid()->toString();
            $this->db->table('dim_sales_channels')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'code' => $code],
                [
                    'id' => $id,
                    'workspace_id' => $workspaceId,
                    'name' => $code,
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $id = $this->findDimId('dim_sales_channels', $workspaceId, 'code', $code) ?? $id;
        }

        return $id;
    }

    /**
     * Upsert dim_regions; returns dimension id.
     */
    private function upsertRegion(string $workspaceId, string $code): string
    {
        $id = $this->findDimId('dim_regions', $workspaceId, 'code', $code);

        if ($id === null) {
            $id = Str::uuid()->toString();
            $this->db->table('dim_regions')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'code' => $code],
                [
                    'id' => $id,
                    'workspace_id' => $workspaceId,
                    'name' => $code,
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $id = $this->findDimId('dim_regions', $workspaceId, 'code', $code) ?? $id;
        }

        return $id;
    }

    /**
     * Upsert dim_warehouses; returns dimension id.
     * region_id is optional (may be unknown when called from inventory path).
     */
    private function upsertWarehouse(string $workspaceId, string $code, ?string $regionId = null): string
    {
        $id = $this->findDimId('dim_warehouses', $workspaceId, 'code', $code);

        if ($id === null) {
            $id = Str::uuid()->toString();
            $resolvedRegionId = $regionId ?? $this->upsertRegion($workspaceId, $code.'_region');
            $this->db->table('dim_warehouses')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'code' => $code],
                [
                    'id' => $id,
                    'workspace_id' => $workspaceId,
                    'region_id' => $resolvedRegionId,
                    'name' => $code,
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $id = $this->findDimId('dim_warehouses', $workspaceId, 'code', $code) ?? $id;
        }

        return $id;
    }

    /**
     * Upsert dim_products; returns dimension id.
     */
    private function upsertProduct(string $workspaceId, string $sku): string
    {
        $id = $this->findDimId('dim_products', $workspaceId, 'sku', $sku);

        if ($id === null) {
            $id = Str::uuid()->toString();
            // category_id and brand_id are required FK — use a default sentinel
            $categoryId = $this->upsertDefaultCategory($workspaceId);
            $brandId = $this->upsertDefaultBrand($workspaceId);

            $this->db->table('dim_products')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'sku' => $sku],
                [
                    'id' => $id,
                    'workspace_id' => $workspaceId,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'sku' => $sku,
                    'name' => $sku,
                    'cost_price' => 0,
                    'unit_price' => 0,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $id = $this->findDimId('dim_products', $workspaceId, 'sku', $sku) ?? $id;
        }

        return $id;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fact upserts
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Upsert fact_orders; returns order id.
     */
    private function upsertOrder(
        string $workspaceId,
        string $orderNumber,
        string $channelId,
        string $regionId,
        string $orderDate,
        string $orderStatus,
    ): string {
        $existing = $this->db->table('fact_orders')
            ->where('workspace_id', $workspaceId)
            ->where('order_number', $orderNumber)
            ->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $id = Str::uuid()->toString();
        $this->db->table('fact_orders')->updateOrInsert(
            ['workspace_id' => $workspaceId, 'order_number' => $orderNumber],
            [
                'id' => $id,
                'workspace_id' => $workspaceId,
                'order_number' => $orderNumber,
                'channel_id' => $channelId,
                'region_id' => $regionId,
                'status' => $orderStatus,
                'ordered_at' => $orderDate.' 00:00:00',
                'order_date' => $orderDate,
                'total_amount' => 0,
                'currency' => 'RUB',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return (string) ($this->db->table('fact_orders')
            ->where('workspace_id', $workspaceId)
            ->where('order_number', $orderNumber)
            ->value('id') ?? $id);
    }

    /**
     * Upsert fact_order_items on (order_id, product_id).
     */
    private function upsertOrderItem(
        string $workspaceId,
        string $orderId,
        string $productId,
        string $warehouseId,
        ?string $categoryId,
        ?string $brandId,
        string $regionId,
        string $channelId,
        string $orderDate,
        int $quantity,
        float $unitPrice,
        float $unitCost,
    ): void {
        $resolvedCategoryId = $categoryId ?? $this->upsertDefaultCategory($workspaceId);
        $resolvedBrandId = $brandId ?? $this->upsertDefaultBrand($workspaceId);
        $totalPrice = $quantity * $unitPrice;
        $totalCost = $quantity * $unitCost;
        $grossProfit = $totalPrice - $totalCost;

        $this->db->table('fact_order_items')->updateOrInsert(
            ['order_id' => $orderId, 'product_id' => $productId],
            [
                'id' => Str::uuid()->toString(),
                'workspace_id' => $workspaceId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'category_id' => $resolvedCategoryId,
                'brand_id' => $resolvedBrandId,
                'region_id' => $regionId,
                'channel_id' => $channelId,
                'order_date' => $orderDate,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
                'total_price' => $totalPrice,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Upsert fact_inventory_daily on (workspace_id, snapshot_date, product_id, warehouse_id).
     */
    private function upsertInventoryDaily(
        string $workspaceId,
        string $snapshotDate,
        string $productId,
        string $warehouseId,
        int $quantityOnHand,
        int $quantityReserved,
        int $safetyStock,
        int $reorderPoint,
        float $unitCost,
    ): void {
        $quantityAvailable = max(0, $quantityOnHand - $quantityReserved);
        $inventoryValue = $quantityOnHand * $unitCost;

        $this->db->table('fact_inventory_daily')->updateOrInsert(
            [
                'workspace_id' => $workspaceId,
                'snapshot_date' => $snapshotDate,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'id' => Str::uuid()->toString(),
                'workspace_id' => $workspaceId,
                'snapshot_date' => $snapshotDate,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity_on_hand' => $quantityOnHand,
                'quantity_reserved' => $quantityReserved,
                'quantity_available' => $quantityAvailable,
                'safety_stock' => $safetyStock,
                'reorder_point' => $reorderPoint,
                'unit_cost' => $unitCost,
                'inventory_value' => $inventoryValue,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Default sentinel dimension helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function upsertDefaultCategory(string $workspaceId): string
    {
        $id = $this->findDimId('dim_categories', $workspaceId, 'slug', 'uncategorized');
        if ($id === null) {
            $id = Str::uuid()->toString();
            $this->db->table('dim_categories')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'slug' => 'uncategorized'],
                [
                    'id' => $id,
                    'workspace_id' => $workspaceId,
                    'name' => 'Uncategorized',
                    'slug' => 'uncategorized',
                    'code' => 'UNCAT',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $id = $this->findDimId('dim_categories', $workspaceId, 'slug', 'uncategorized') ?? $id;
        }

        return $id;
    }

    private function upsertDefaultBrand(string $workspaceId): string
    {
        $id = $this->findDimId('dim_brands', $workspaceId, 'name', 'Unknown');
        if ($id === null) {
            $id = Str::uuid()->toString();
            $this->db->table('dim_brands')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'name' => 'Unknown'],
                [
                    'id' => $id,
                    'workspace_id' => $workspaceId,
                    'name' => 'Unknown',
                    'country' => 'N/A',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $id = $this->findDimId('dim_brands', $workspaceId, 'name', 'Unknown') ?? $id;
        }

        return $id;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generic helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function findDimId(string $table, string $workspaceId, string $column, string $value): ?string
    {
        $result = $this->db->table($table)
            ->where('workspace_id', $workspaceId)
            ->where($column, $value)
            ->value('id');

        return $result !== null ? (string) $result : null;
    }

    private function resolveSeason(int $month): string
    {
        return match (true) {
            $month >= 3 && $month <= 5 => 'spring',
            $month >= 6 && $month <= 8 => 'summer',
            $month >= 9 && $month <= 11 => 'autumn',
            default => 'winter',
        };
    }
}
