<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Commands;

use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesCommand;
use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesHandler;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use Illuminate\Console\Command;

final class EvaluateAlertsConsoleCommand extends Command
{
    protected $signature = 'alerts:evaluate {--workspace= : Specific workspace ID to evaluate}';

    protected $description = 'Evaluate active alerting rules against latest inventory state';

    public function handle(
        EvaluateAlertRulesHandler $handler,
        WorkspaceRepositoryInterface $workspaceRepository
    ): int {
        $workspaceId = $this->option('workspace');

        if ($workspaceId !== null && $workspaceId !== '') {
            $workspaces = [$workspaceId];
        } else {
            $all = $workspaceRepository->findAll();
            $workspaces = array_map(fn ($ws) => $ws->id()->value(), $all);
        }

        foreach ($workspaces as $wsId) {
            $this->info("Evaluating alert rules for workspace: {$wsId}...");
            $result = $handler->handle(new EvaluateAlertRulesCommand($wsId));
            $this->info("  Rules evaluated: {$result->rulesEvaluated}");
            $this->info("  Alerts triggered: {$result->alertsTriggered}");
            $this->info("  Alerts created: {$result->alertsCreated}");
            $this->info("  Alerts updated: {$result->alertsUpdated}");
        }

        return self::SUCCESS;
    }
}
