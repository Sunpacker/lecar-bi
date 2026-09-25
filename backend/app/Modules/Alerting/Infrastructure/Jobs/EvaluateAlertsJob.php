<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Jobs;

use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesCommand;
use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class EvaluateAlertsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        private string $workspaceId,
        private ?string $ruleId = null,
        private ?string $warehouseId = null,
    ) {}

    public function handle(EvaluateAlertRulesHandler $handler): void
    {
        $handler->handle(new EvaluateAlertRulesCommand(
            workspaceId: $this->workspaceId,
            ruleId: $this->ruleId,
            warehouseId: $this->warehouseId,
        ));
    }
}
