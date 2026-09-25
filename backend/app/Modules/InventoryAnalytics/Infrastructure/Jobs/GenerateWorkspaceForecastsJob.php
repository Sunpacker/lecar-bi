<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateWorkspaceForecastsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $workspaceId,
        public int $horizonDays = 28
    ) {
        $this->onQueue('forecasting');
    }

    public function uniqueId(): string
    {
        return "{$this->workspaceId}:{$this->horizonDays}";
    }

    public function handle(): void
    {
        $latestSnapshotDate = DB::table('fact_inventory_daily')
            ->where('workspace_id', $this->workspaceId)
            ->max('snapshot_date');

        if (! $latestSnapshotDate) {
            return;
        }

        DB::table('fact_inventory_daily')
            ->where('workspace_id', $this->workspaceId)
            ->where('snapshot_date', $latestSnapshotDate)
            ->select('product_id', 'warehouse_id')
            ->distinct()
            ->orderBy('product_id')
            ->chunk(50, function ($records) {
                foreach ($records as $record) {
                    GenerateForecastJob::dispatch(
                        $this->workspaceId,
                        $record->product_id,
                        $record->warehouse_id,
                        $this->horizonDays
                    );
                }
            });
    }
}
