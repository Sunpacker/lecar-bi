<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Shared\Infrastructure\Performance\AnalyticsBenchmarkRunner;
use App\Shared\Infrastructure\Performance\BenchmarkScenario;
use App\Shared\Infrastructure\Performance\ExplainPlanCollector;
use Database\Seeders\Performance\PerformanceDatasetProfile;
use Illuminate\Console\Command;

final class BenchmarkAnalyticsCommand extends Command
{
    protected $signature = 'performance:benchmark
        {--profile=large : Dataset profile name (small or large)}
        {--workspace=perf-ws-1 : Target isolated performance workspace ID (must start with perf-)}
        {--runs=30 : Number of measured benchmark iterations}
        {--warmup=5 : Number of warmup iterations}
        {--cache-state=disabled : Cache state to evaluate (disabled, cold, or warm)}
        {--format=json : Output format (json or table)}
        {--scenario= : Optional single scenario ID to run (e.g. SALES-01)}
        {--output= : Optional file path to write JSON results}
        {--explain : Collect EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) for captured queries}';

    protected $description = 'Execute analytics benchmark scenarios and collect latency, query counts, and explain plans';

    public function handle(AnalyticsBenchmarkRunner $runner, ExplainPlanCollector $explainCollector): int
    {
        // 1. Safety check: strictly restrict to local and testing environments
        if (! app()->environment(['local', 'testing'])) {
            $this->error('CRITICAL SAFETY ERROR: performance:benchmark is strictly restricted to local and testing environments.');

            return self::FAILURE;
        }

        // 2. Safety check: workspace must start with perf-
        /** @var string $workspaceId */
        $workspaceId = (string) $this->option('workspace');
        if (! str_starts_with($workspaceId, 'perf-')) {
            $this->error("SAFETY ERROR: Target workspace ID must start with 'perf-'. Refusing to touch workspace '{$workspaceId}'.");

            return self::FAILURE;
        }

        /** @var string $profileName */
        $profileName = (string) $this->option('profile');
        try {
            $profile = PerformanceDatasetProfile::fromName($profileName);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $runs = (int) $this->option('runs');
        $warmup = (int) $this->option('warmup');
        $cacheState = strtolower((string) $this->option('cache-state'));
        $format = strtolower((string) $this->option('format'));
        $scenarioFilter = $this->option('scenario') !== null ? (string) $this->option('scenario') : null;
        $outputPath = $this->option('output') !== null ? (string) $this->option('output') : null;
        $explain = (bool) $this->option('explain');

        if (! in_array($cacheState, ['disabled', 'cold', 'warm'], true)) {
            $this->error("Invalid cache-state '{$cacheState}'. Must be disabled, cold, or warm.");

            return self::FAILURE;
        }

        config(['analytics.cache_enabled' => ($cacheState !== 'disabled')]);
        app()->forgetInstance(SalesAnalyticsReadModelInterface::class);
        app()->forgetInstance(InventoryAnalyticsReadModelInterface::class);
        app()->forgetInstance(SupplierAnalyticsReadModelInterface::class);

        $allScenarios = BenchmarkScenario::all($workspaceId);

        if ($scenarioFilter !== null) {
            if (! isset($allScenarios[$scenarioFilter])) {
                $valid = implode(', ', array_keys($allScenarios));
                $this->error("Unknown scenario ID '{$scenarioFilter}'. Valid scenarios: {$valid}.");

                return self::FAILURE;
            }
            $scenariosToRun = [$scenarioFilter => $allScenarios[$scenarioFilter]];
        } else {
            $scenariosToRun = $allScenarios;
        }

        $results = [];
        $startTime = hrtime(true);

        foreach ($scenariosToRun as $scenario) {
            // Check if scenario supports requested cache state
            if (! in_array($cacheState, $scenario->supportedCacheStates, true)) {
                $results[] = [
                    'scenario_id' => $scenario->id,
                    'name' => $scenario->name,
                    'module' => $scenario->module,
                    'cache_state' => $cacheState,
                    'warmup_runs' => $warmup,
                    'measured_runs' => $runs,
                    'p50_ms' => 0.0,
                    'p95_ms' => 0.0,
                    'min_ms' => 0.0,
                    'max_ms' => 0.0,
                    'avg_ms' => 0.0,
                    'query_count' => 0,
                    'budget_p95_ms' => $scenario->budgetP95Ms,
                    'status' => 'SKIPPED',
                    'note' => "Cache state '{$cacheState}' not applicable for this scenario",
                    'explain_plans' => [],
                ];

                continue;
            }

            $scenarioResult = $runner->runScenario(
                scenario: $scenario,
                runs: $runs,
                warmup: $warmup,
                cacheState: $cacheState,
                progressCallback: function (BenchmarkScenario $s, string $phase, int $current, int $total) use ($format): void {
                    if ($format === 'table' && ($current === 1 || $current === $total || $current % 10 === 0)) {
                        $this->line(sprintf('  [%s] %s: %s %d/%d', $s->id, $s->name, $phase, $current, $total));
                    }
                }
            );

            // Collect EXPLAIN plans if requested
            $explainPlans = [];
            if ($explain && ! empty($scenarioResult['captured_queries'])) {
                foreach ($scenarioResult['captured_queries'] as $q) {
                    if (ExplainPlanCollector::isReadOnlyQuery($q['query'])) {
                        try {
                            $explainPlans[] = $explainCollector->explain($q['query'], $q['bindings']);
                        } catch (\Throwable $e) {
                            $explainPlans[] = [
                                'sql' => $q['query'],
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                }
            }

            $scenarioResult['explain_plans'] = $explainPlans;
            unset($scenarioResult['captured_queries']);

            $results[] = $scenarioResult;
        }

        $elapsedSeconds = round((hrtime(true) - $startTime) / 1_000_000_000.0, 2);

        $payload = [
            'benchmark_metadata' => [
                'timestamp' => date('Y-m-d H:i:s T'),
                'profile' => $profile->value,
                'workspace_id' => $workspaceId,
                'cache_state' => $cacheState,
                'runs' => $runs,
                'warmup' => $warmup,
                'total_scenarios' => count($results),
                'elapsed_seconds' => $elapsedSeconds,
            ],
            'scenarios' => $results,
        ];

        if ($outputPath !== null) {
            $dir = dirname($outputPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->newLine();
            $this->info("=== Benchmark Results ({$elapsedSeconds}s) ===");
            $rows = array_map(function ($r) {
                return [
                    $r['scenario_id'],
                    $r['name'],
                    $r['cache_state'],
                    $r['p50_ms'] > 0 ? sprintf('%.2f ms', $r['p50_ms']) : 'N/A',
                    $r['p95_ms'] > 0 ? sprintf('%.2f ms', $r['p95_ms']) : 'N/A',
                    $r['min_ms'] > 0 ? sprintf('%.2f ms', $r['min_ms']) : 'N/A',
                    $r['max_ms'] > 0 ? sprintf('%.2f ms', $r['max_ms']) : 'N/A',
                    $r['avg_ms'] > 0 ? sprintf('%.2f ms', $r['avg_ms']) : 'N/A',
                    $r['query_count'],
                    sprintf('≤ %d ms', $r['budget_p95_ms']),
                    $r['status'],
                ];
            }, $results);

            $this->table(
                ['ID', 'Name', 'Cache', 'p50', 'p95', 'Min', 'Max', 'Avg', 'Queries', 'Budget', 'Status'],
                $rows
            );
        }

        return self::SUCCESS;
    }
}
