<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Shared\Infrastructure\Performance\AnalyticsBenchmarkRunner;
use App\Shared\Infrastructure\Performance\BenchmarkScenario;
use App\Shared\Infrastructure\Performance\ExplainPlanCollector;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BenchmarkScenarioTest extends TestCase
{
    #[Test]
    public function all_23_scenarios_are_registered_and_configured(): void
    {
        $scenarios = BenchmarkScenario::all('perf-ws-1');

        self::assertCount(23, $scenarios);

        $expectedIds = [
            'SALES-01', 'SALES-02', 'SALES-03', 'SALES-04', 'SALES-05',
            'INV-01', 'INV-02', 'INV-03', 'INV-04', 'INV-05', 'INV-06', 'INV-07', 'INV-08', 'INV-09',
            'SUP-01', 'SUP-02', 'SUP-03', 'SUP-04', 'SUP-05', 'SUP-06', 'SUP-07',
            'DASH-01', 'DASH-02',
        ];

        foreach ($expectedIds as $id) {
            self::assertArrayHasKey($id, $scenarios, "Scenario {$id} must be present in catalogue.");
            $scenario = $scenarios[$id];

            self::assertSame($id, $scenario->id);
            self::assertNotEmpty($scenario->name);
            self::assertNotEmpty($scenario->module);
            self::assertGreaterThan(0, $scenario->budgetP95Ms);
            self::assertTrue($scenario->isReadOnly);
            self::assertNotEmpty($scenario->supportedCacheStates);

            foreach ($scenario->supportedCacheStates as $cacheState) {
                self::assertContains($cacheState, ['disabled', 'cold', 'warm']);
            }
        }
    }

    #[Test]
    public function scenario_lookup_helpers_work_correctly(): void
    {
        $found = BenchmarkScenario::find('SALES-01', 'perf-ws-1');
        self::assertNotNull($found);
        self::assertSame('SALES-01', $found->id);

        $notFound = BenchmarkScenario::find('UNKNOWN-99', 'perf-ws-1');
        self::assertNull($notFound);

        $scenario = BenchmarkScenario::get('INV-01', 'perf-ws-1');
        self::assertSame('INV-01', $scenario->id);

        $this->expectException(InvalidArgumentException::class);
        BenchmarkScenario::get('UNKNOWN-99', 'perf-ws-1');
    }

    #[Test]
    public function runner_percentile_calculation_is_accurate(): void
    {
        $data = [10.0, 20.0, 30.0, 40.0, 50.0];

        self::assertSame(30.0, AnalyticsBenchmarkRunner::computePercentile($data, 50.0));
        self::assertSame(10.0, AnalyticsBenchmarkRunner::computePercentile($data, 0.0));
        self::assertSame(50.0, AnalyticsBenchmarkRunner::computePercentile($data, 100.0));
        self::assertSame(48.0, AnalyticsBenchmarkRunner::computePercentile($data, 95.0));

        // Single element
        self::assertSame(42.0, AnalyticsBenchmarkRunner::computePercentile([42.0], 95.0));

        // Empty array
        self::assertSame(0.0, AnalyticsBenchmarkRunner::computePercentile([], 95.0));
    }

    #[Test]
    public function explain_collector_validates_read_only_queries(): void
    {
        self::assertTrue(ExplainPlanCollector::isReadOnlyQuery('SELECT * FROM fact_orders WHERE workspace_id = ?'));
        self::assertTrue(ExplainPlanCollector::isReadOnlyQuery('WITH cte AS (SELECT id FROM fact_orders) SELECT * FROM cte'));
        self::assertTrue(ExplainPlanCollector::isReadOnlyQuery('/* comment */ SELECT COUNT(*) FROM dim_products'));

        self::assertFalse(ExplainPlanCollector::isReadOnlyQuery('INSERT INTO fact_orders (id) VALUES (1)'));
        self::assertFalse(ExplainPlanCollector::isReadOnlyQuery('UPDATE fact_orders SET total_amount = 0'));
        self::assertFalse(ExplainPlanCollector::isReadOnlyQuery('DELETE FROM fact_orders WHERE id = 1'));
        self::assertFalse(ExplainPlanCollector::isReadOnlyQuery('DROP TABLE fact_orders'));
        self::assertFalse(ExplainPlanCollector::isReadOnlyQuery('TRUNCATE fact_orders'));
        self::assertFalse(ExplainPlanCollector::isReadOnlyQuery('ALTER TABLE fact_orders DROP COLUMN id'));
        self::assertFalse(ExplainPlanCollector::isReadOnlyQuery(''));
    }

    #[Test]
    public function runner_executes_scenario_and_produces_valid_metrics(): void
    {
        $executedCount = 0;
        $scenario = new BenchmarkScenario(
            id: 'TEST-RUN-01',
            name: 'Test Execution Scenario',
            module: 'TestModule',
            budgetP95Ms: 1000,
            supportedCacheStates: ['disabled'],
            executor: function () use (&$executedCount) {
                $executedCount++;
                usleep(500); // 0.5ms
            },
        );

        $runner = new AnalyticsBenchmarkRunner;
        $result = $runner->runScenario($scenario, runs: 10, warmup: 3);

        self::assertSame('TEST-RUN-01', $result['scenario_id']);
        self::assertSame(10, $result['measured_runs']);
        self::assertSame(3, $result['warmup_runs']);
        self::assertSame(13, $executedCount, 'Total executions must equal warmup + runs.');
        self::assertGreaterThan(0.0, $result['p50_ms']);
        self::assertGreaterThanOrEqual($result['min_ms'], $result['p50_ms']);
        self::assertGreaterThanOrEqual($result['p50_ms'], $result['p95_ms']);
        self::assertGreaterThanOrEqual($result['p95_ms'], $result['max_ms']);
        self::assertSame('PASS', $result['status']);
    }

    #[Test]
    public function explain_collector_sanitizes_sensitive_bindings(): void
    {
        $raw = ['normal_string', 'secret_token_abc123', 42, 'user_password_hash'];
        $sanitized = ExplainPlanCollector::sanitizeBindings($raw);

        self::assertSame('normal_string', $sanitized[0]);
        self::assertSame('[REDACTED]', $sanitized[1]);
        self::assertSame(42, $sanitized[2]);
        self::assertSame('[REDACTED]', $sanitized[3]);
    }

    #[Test]
    public function explain_collector_rejects_mutation_query(): void
    {
        $collector = new ExplainPlanCollector;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EXPLAIN plan collection is only allowed for read-only SELECT or WITH queries.');

        $collector->explain('DELETE FROM fact_orders WHERE workspace_id = ?', ['perf-ws-1']);
    }
}
