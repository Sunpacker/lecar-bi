<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\SalesTimeSeriesReaderInterface;
use App\Modules\InventoryAnalytics\Domain\Forecasting\DailySalesPoint;
use App\Modules\InventoryAnalytics\Domain\Forecasting\SalesTimeSeries;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class PostgresSalesTimeSeriesReader implements SalesTimeSeriesReaderInterface
{
    public function getDailySalesTimeSeries(
        string $workspaceId,
        string $productId,
        string $warehouseId,
        string $asOfDate,
        int $lookbackDays = 365
    ): SalesTimeSeries {
        $startDate = (new DateTimeImmutable($asOfDate))->modify("-{$lookbackDays} days")->format('Y-m-d');

        /** @var array<int, object{sale_date: string, daily_quantity: numeric, is_stockout: bool}> $results */
        $results = DB::select('
            SELECT d.date as sale_date,
                   COALESCE(SUM(foi.quantity), 0) as daily_quantity,
                   CASE WHEN fi.quantity_available = 0 THEN true ELSE false END as is_stockout
            FROM dim_dates d
            LEFT JOIN fact_order_items foi ON foi.order_date = d.date
                 AND foi.workspace_id = ? AND foi.product_id = ? AND foi.warehouse_id = ?
            LEFT JOIN fact_inventory_daily fi ON fi.snapshot_date = d.date
                 AND fi.workspace_id = ? AND fi.product_id = ? AND fi.warehouse_id = ?
            WHERE d.date BETWEEN ? AND ?
            GROUP BY d.date, fi.quantity_available
            ORDER BY d.date
        ', [
            $workspaceId, $productId, $warehouseId,
            $workspaceId, $productId, $warehouseId,
            $startDate, $asOfDate,
        ]);

        $points = [];
        foreach ($results as $row) {
            $points[] = new DailySalesPoint(
                new DateTimeImmutable((string) $row->sale_date),
                (float) $row->daily_quantity,
                (bool) $row->is_stockout
            );
        }

        return new SalesTimeSeries(
            $productId,
            $warehouseId,
            new DateTimeImmutable($asOfDate),
            $points
        );
    }
}
