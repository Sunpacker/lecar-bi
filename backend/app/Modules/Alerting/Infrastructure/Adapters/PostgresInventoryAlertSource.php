<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Adapters;

use App\Modules\Alerting\Application\Contracts\InventoryAlertSourceInterface;
use App\Modules\Alerting\Application\Dtos\InventoryCandidateDto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PostgresInventoryAlertSource implements InventoryAlertSourceInterface
{
    /**
     * @return list<InventoryCandidateDto>
     */
    public function getInventoryCandidates(string $workspaceId, ?string $warehouseId = null): array
    {
        $latestSnapshot = DB::table('fact_inventory_daily')
            ->where('workspace_id', $workspaceId)
            ->max('snapshot_date');

        if ($latestSnapshot === null) {
            return [];
        }

        $query = DB::table('fact_inventory_daily as inv')
            ->join('dim_products as p', function ($join) {
                $join->on('p.id', '=', 'inv.product_id')
                    ->on('p.workspace_id', '=', 'inv.workspace_id');
            })
            ->join('dim_warehouses as w', function ($join) {
                $join->on('w.id', '=', 'inv.warehouse_id')
                    ->on('w.workspace_id', '=', 'inv.workspace_id');
            })
            ->leftJoin('dim_categories as c', function ($join) {
                $join->on('c.id', '=', 'p.category_id')
                    ->on('c.workspace_id', '=', 'inv.workspace_id');
            })
            ->where('inv.workspace_id', $workspaceId)
            ->where('inv.snapshot_date', $latestSnapshot);

        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('inv.warehouse_id', $warehouseId);
        }

        $rows = $query->select([
            'inv.product_id',
            'p.name as product_name',
            'p.sku as product_sku',
            'p.category_id',
            'c.name as category_name',
            'inv.warehouse_id',
            'w.name as warehouse_name',
            'inv.quantity_on_hand',
            'inv.quantity_reserved',
            'inv.quantity_available',
            'inv.safety_stock',
            'inv.reorder_point',
            'inv.unit_cost',
            'inv.inventory_value',
        ])->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Calculate 30-day velocity from fact_order_items for these products
        $snapshotDate = Carbon::parse($latestSnapshot);
        $startDate = $snapshotDate->copy()->subDays(30)->toDateString();

        $salesVelocity = DB::table('fact_order_items')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('order_date', [$startDate, $latestSnapshot])
            ->groupBy('product_id', 'warehouse_id')
            ->select([
                'product_id',
                'warehouse_id',
                DB::raw('ROUND(SUM(quantity)::numeric / 30.0, 2) as daily_velocity'),
            ])
            ->get()
            ->keyBy(fn ($item) => $item->product_id.':'.$item->warehouse_id);

        $result = [];
        foreach ($rows as $row) {
            $key = $row->product_id.':'.$row->warehouse_id;
            $velocity = isset($salesVelocity[$key]) ? (float) $salesVelocity[$key]->daily_velocity : 0.0;

            $available = (int) $row->quantity_available;
            $daysOfStock = null;
            if ($available <= 0) {
                $daysOfStock = 0.0;
            } elseif ($velocity > 0.0) {
                $daysOfStock = round($available / $velocity, 1);
            }

            $result[] = new InventoryCandidateDto(
                productId: (string) $row->product_id,
                productName: (string) $row->product_name,
                productSku: (string) $row->product_sku,
                categoryId: $row->category_id !== null ? (string) $row->category_id : null,
                categoryName: $row->category_name !== null ? (string) $row->category_name : null,
                warehouseId: (string) $row->warehouse_id,
                warehouseName: (string) $row->warehouse_name,
                quantityOnHand: (int) $row->quantity_on_hand,
                quantityReserved: (int) $row->quantity_reserved,
                quantityAvailable: $available,
                safetyStock: (int) $row->safety_stock,
                reorderPoint: (int) $row->reorder_point,
                unitCost: (float) $row->unit_cost,
                inventoryValue: (float) $row->inventory_value,
                dailyVelocity: $velocity,
                daysOfStock: $daysOfStock,
            );
        }

        return $result;
    }
}
