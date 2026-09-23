<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries;

use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Domain\StockHealthStatus;
use Illuminate\Database\Query\Builder;

class InventoryItemsQuery
{
    use InteractsWithInventorySnapshot;

    public function execute(string $workspaceId, InventoryItemsCriteriaDto $criteria): InventoryItemsPaginatedDto
    {
        $asOfDate = $this->resolveLatestSnapshotDate($workspaceId);

        if (! $asOfDate) {
            return new InventoryItemsPaginatedDto(items: [], total: 0, page: $criteria->page, perPage: $criteria->perPage, totalPages: 0);
        }

        $query = $this->baseSnapshotQuery($workspaceId, $asOfDate, $criteria->warehouseId);

        if ($criteria->stockHealth !== null && $criteria->stockHealth !== '') {
            $query->where('inv.health_status', $criteria->stockHealth);
        }

        if ($criteria->search !== null && $criteria->search !== '') {
            $searchTerm = '%'.mb_strtolower(trim($criteria->search)).'%';
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->whereRaw('LOWER(p.name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(p.sku) LIKE ?', [$searchTerm]);
            });
        }

        $sortField = match ($criteria->sortBy) {
            'quantity_on_hand' => 'inv.quantity_on_hand',
            'quantity_available' => 'inv.quantity_available',
            'inventory_value' => 'inv.inventory_value',
            'sales_velocity' => 'inv.sales_velocity',
            'days_of_stock' => 'inv.days_of_stock',
            default => 'p.name',
        };

        $sortDirection = $criteria->sortDirection === 'desc' ? 'desc' : 'asc';

        $perPage = max(1, $criteria->perPage);
        $page = max(1, $criteria->page);
        $offset = ($page - 1) * $perPage;

        $rows = (clone $query)
            ->selectRaw('
                inv.id,
                inv.product_id,
                p.name as product_name,
                p.sku as product_sku,
                p.category_id,
                c.name as category_name,
                inv.warehouse_id,
                w.name as warehouse_name,
                w.code as warehouse_code,
                inv.quantity_on_hand,
                inv.quantity_reserved,
                inv.quantity_available,
                inv.unit_cost,
                inv.inventory_value,
                inv.sales_velocity,
                inv.days_of_stock,
                inv.health_status,
                inv.safety_stock,
                inv.reorder_point,
                COUNT(*) OVER() as full_count
            ')
            ->orderBy($sortField, $sortDirection)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $total = count($rows) > 0 ? (int) $rows[0]->full_count : 0;
        $totalPages = (int) ceil($total / $perPage);

        $items = [];
        foreach ($rows as $row) {
            $healthStatus = StockHealthStatus::tryFrom($row->health_status) ?? StockHealthStatus::OPTIMAL;

            $items[] = new InventoryItemDto(
                id: (string) $row->id,
                productId: (string) $row->product_id,
                productName: (string) $row->product_name,
                productSku: (string) $row->product_sku,
                categoryId: (string) $row->category_id,
                categoryName: (string) $row->category_name,
                warehouseId: (string) $row->warehouse_id,
                warehouseName: (string) $row->warehouse_name,
                warehouseCode: (string) $row->warehouse_code,
                quantityOnHand: (int) $row->quantity_on_hand,
                quantityReserved: (int) $row->quantity_reserved,
                quantityAvailable: (int) $row->quantity_available,
                unitCost: (float) $row->unit_cost,
                inventoryValue: (float) $row->inventory_value,
                salesVelocity: (float) $row->sales_velocity,
                daysOfStock: $row->days_of_stock !== null ? (float) $row->days_of_stock : null,
                stockHealth: $healthStatus->value,
                stockHealthLabel: $healthStatus->label(),
                safetyStock: (int) $row->safety_stock,
                reorderPoint: (int) $row->reorder_point,
            );
        }

        return new InventoryItemsPaginatedDto(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }
}
