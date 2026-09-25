<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\ForecastReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastPointDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastQualityDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastStockRiskDto;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class PostgresForecastReadModel implements ForecastReadModelInterface
{
    public function getLatestForecast(
        string $workspaceId,
        string $productId,
        string $warehouseId,
        int $horizonDays
    ): ?ForecastDto {
        $forecast = DB::table('forecasts')
            ->join('dim_products', 'forecasts.product_id', '=', 'dim_products.id')
            ->join('dim_warehouses', 'forecasts.warehouse_id', '=', 'dim_warehouses.id')
            ->where('forecasts.workspace_id', $workspaceId)
            ->where('forecasts.product_id', $productId)
            ->where('forecasts.warehouse_id', $warehouseId)
            ->where('forecasts.horizon_days', $horizonDays)
            ->orderByDesc('forecasts.generated_at')
            ->select(
                'forecasts.*',
                'dim_products.name as product_name',
                'dim_products.sku as product_sku',
                'dim_warehouses.name as warehouse_name'
            )
            ->first();

        if (! $forecast) {
            return null;
        }

        $points = DB::table('forecast_points')
            ->where('forecast_id', $forecast->id)
            ->orderBy('forecast_date')
            ->get()
            ->map(fn ($p) => new ForecastPointDto(
                (string) $p->forecast_date,
                (float) $p->point_estimate,
                $p->lower_bound !== null ? (float) $p->lower_bound : null,
                $p->upper_bound !== null ? (float) $p->upper_bound : null,
                $p->interval_level !== null ? (float) $p->interval_level : null,
                $p->actual_value !== null ? (float) $p->actual_value : null,
                (bool) $p->is_stockout_day
            ))
            ->toArray();

        $metrics = DB::table('forecast_quality_metrics')
            ->where('forecast_id', $forecast->id)
            ->get()
            ->map(fn ($m) => new ForecastQualityDto(
                (string) $m->metric_name,
                (float) $m->metric_value,
                (int) $m->horizon_days,
                $m->segment !== null ? (string) $m->segment : null,
                (int) $m->evaluation_windows
            ))
            ->toArray();

        $riskRecord = DB::table('forecast_stock_risk')
            ->where('forecast_id', $forecast->id)
            ->first();

        $stockRisk = null;
        if ($riskRecord) {
            $stockRisk = new ForecastStockRiskDto(
                (int) $riskRecord->current_quantity_available,
                (int) $riskRecord->current_safety_stock,
                (int) $riskRecord->current_reorder_point,
                $riskRecord->estimated_depletion_date !== null ? (string) $riskRecord->estimated_depletion_date : null,
                $riskRecord->estimated_reorder_threshold_date !== null ? (string) $riskRecord->estimated_reorder_threshold_date : null,
                $riskRecord->estimated_order_placement_date !== null ? (string) $riskRecord->estimated_order_placement_date : null,
                $riskRecord->median_lead_time_days !== null ? (int) $riskRecord->median_lead_time_days : null,
                $riskRecord->lead_time_source !== null ? (string) $riskRecord->lead_time_source : null,
                (string) $riskRecord->assumptions
            );
        }

        $historyStartDate = (new DateTimeImmutable((string) $forecast->as_of_date))
            ->modify('-28 days')
            ->format('Y-m-d');

        $measuredHistory = DB::table('fact_order_items')
            ->where('workspace_id', $workspaceId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('order_date', '<=', $forecast->as_of_date)
            ->where('order_date', '>=', $historyStartDate)
            ->selectRaw('order_date as sale_date, sum(quantity) as qty')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->map(fn ($row) => new ForecastPointDto(
                (string) $row->sale_date,
                (float) $row->qty,
                null,
                null,
                null,
                (float) $row->qty,
                false
            ))
            ->toArray();

        return new ForecastDto(
            (string) $forecast->id,
            (string) $forecast->workspace_id,
            (string) $forecast->product_id,
            (string) $forecast->product_name,
            (string) $forecast->product_sku,
            (string) $forecast->warehouse_id,
            (string) $forecast->warehouse_name,
            (string) $forecast->as_of_date,
            new DateTimeImmutable((string) $forecast->generated_at),
            (int) $forecast->horizon_days,
            (string) $forecast->model_method,
            (string) $forecast->model_version,
            (string) $forecast->status,
            $forecast->status_reason !== null ? (string) $forecast->status_reason : null,
            $points,
            $measuredHistory,
            $metrics,
            $stockRisk,
            (string) $forecast->as_of_date,
            (string) $forecast->as_of_date,
            (string) $forecast->as_of_date,
            [
                'По умолчанию предполагается отсутствие будущих неподтвержденных поставок при расчете дефицита',
                'Дни нулевых остатков исключены из расчета базового спроса (децензурирование)',
            ]
        );
    }
}
