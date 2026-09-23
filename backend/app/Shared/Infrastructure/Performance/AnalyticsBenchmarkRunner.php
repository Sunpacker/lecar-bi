<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Performance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;

final class AnalyticsBenchmarkRunner
{
    /**
     * @param  (callable(BenchmarkScenario $scenario, string $phase, int $current, int $total): void)|null  $progressCallback
     * @return array{
     *     scenario_id: string,
     *     name: string,
     *     module: string,
     *     cache_state: string,
     *     warmup_runs: int,
     *     measured_runs: int,
     *     p50_ms: float,
     *     p95_ms: float,
     *     min_ms: float,
     *     max_ms: float,
     *     avg_ms: float,
     *     query_count: int,
     *     budget_p95_ms: int,
     *     status: string,
     *     captured_queries: list<array{query: string, bindings: list<mixed>, time_ms: float}>
     * }
     */
    public function runScenario(
        BenchmarkScenario $scenario,
        int $runs = 30,
        int $warmup = 5,
        string $cacheState = 'disabled',
        ?callable $progressCallback = null
    ): array {
        if ($runs < 1) {
            throw new InvalidArgumentException("Runs count must be >= 1. Given: {$runs}.");
        }

        // 1. Warm-up iterations (OPcache, DB connection, shared buffers warming)
        for ($w = 1; $w <= $warmup; $w++) {
            if ($progressCallback !== null) {
                $progressCallback($scenario, 'warmup', $w, $warmup);
            }
            ($scenario->executor)();
        }

        // 2. Measured iterations
        $durations = [];
        $capturedQueries = [];
        $lastQueryCount = 0;

        $app = Facade::getFacadeApplication();
        $hasDb = $app !== null && $app->bound('db');

        for ($r = 1; $r <= $runs; $r++) {
            if ($progressCallback !== null) {
                $progressCallback($scenario, 'measured', $r, $runs);
            }

            if ($hasDb) {
                DB::flushQueryLog();
                DB::enableQueryLog();
            }

            $start = hrtime(true);
            ($scenario->executor)();
            $elapsedMs = (hrtime(true) - $start) / 1_000_000.0;

            $durations[] = round($elapsedMs, 2);

            $rawQueries = $hasDb ? DB::getQueryLog() : [];
            if ($hasDb) {
                DB::disableQueryLog();
            }

            $lastQueryCount = count($rawQueries);

            // Capture queries on first measured run for explain analysis
            if ($r === 1) {
                $capturedQueries = array_map(fn ($q) => [
                    'query' => (string) $q['query'],
                    'bindings' => (array) $q['bindings'],
                    'time_ms' => (float) ($q['time'] ?? 0.0),
                ], $rawQueries);
            }
        }

        sort($durations);

        $min = round(min($durations), 2);
        $max = round(max($durations), 2);
        $avg = round(array_sum($durations) / count($durations), 2);
        $p50 = self::computePercentile($durations, 50.0);
        $p95 = self::computePercentile($durations, 95.0);

        $status = ($p95 <= $scenario->budgetP95Ms) ? 'PASS' : 'FAIL';

        return [
            'scenario_id' => $scenario->id,
            'name' => $scenario->name,
            'module' => $scenario->module,
            'cache_state' => $cacheState,
            'warmup_runs' => $warmup,
            'measured_runs' => $runs,
            'p50_ms' => $p50,
            'p95_ms' => $p95,
            'min_ms' => $min,
            'max_ms' => $max,
            'avg_ms' => $avg,
            'query_count' => $lastQueryCount,
            'budget_p95_ms' => $scenario->budgetP95Ms,
            'status' => $status,
            'captured_queries' => $capturedQueries,
        ];
    }

    /**
     * Computes percentile with linear interpolation.
     *
     * @param  list<float>  $sorted
     */
    public static function computePercentile(array $sorted, float $p): float
    {
        $count = count($sorted);
        if ($count === 0) {
            return 0.0;
        }
        if ($count === 1) {
            return (float) $sorted[0];
        }

        $index = ($p / 100.0) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $fraction = $index - $lower;

        return round($sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $fraction, 2);
    }
}
