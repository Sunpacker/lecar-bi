<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries;

use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Domain\StockHealthStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryFilterOptionsQuery
{
    use InteractsWithInventorySnapshot;

    public function execute(string $workspaceId): InventoryFilterOptionsDto
    {
        $warehouses = DB::table('dim_warehouses')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn ($wh) => [
                'id' => (string) $wh->id,
                'name' => (string) $wh->name,
                'code' => (string) $wh->code,
            ])
            ->all();

        $statuses = array_map(fn (StockHealthStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], StockHealthStatus::cases());

        $latestDate = $this->resolveLatestSnapshotDate($workspaceId) ?? Carbon::today()->toDateString();

        $categories = DB::table('dim_categories')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => (string) $cat->id,
                'name' => (string) $cat->name,
                'code' => (string) $cat->code,
            ])
            ->all();

        $suppliers = DB::table('dim_suppliers')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($sup) => [
                'id' => (string) $sup->id,
                'name' => (string) $sup->name,
            ])
            ->all();

        return new InventoryFilterOptionsDto(
            warehouses: $warehouses,
            statuses: $statuses,
            latestSnapshotDate: $latestDate,
            categories: $categories,
            suppliers: $suppliers,
        );
    }
}
