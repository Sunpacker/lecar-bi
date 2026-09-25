<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Jobs;

use App\Modules\InventoryAnalytics\Application\Commands\GenerateForecastCommand;
use App\Modules\InventoryAnalytics\Application\Commands\GenerateForecastHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateForecastJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public string $workspaceId,
        public string $productId,
        public string $warehouseId,
        public int $horizonDays
    ) {
        $this->onQueue('forecasting');
    }

    public function uniqueId(): string
    {
        return "{$this->workspaceId}:{$this->productId}:{$this->warehouseId}:{$this->horizonDays}";
    }

    public function handle(GenerateForecastHandler $handler): void
    {
        $command = new GenerateForecastCommand(
            $this->workspaceId,
            $this->productId,
            $this->warehouseId,
            $this->horizonDays
        );

        $handler->handle($command);
    }
}
