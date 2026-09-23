<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Performance;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ExplainPlanCollector
{
    /**
     * Collects EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) for a read-only SQL query with sanitized bindings.
     *
     * @param  list<mixed>  $bindings
     * @return array{
     *     sql: string,
     *     sanitized_bindings: list<mixed>,
     *     execution_time_ms: float|null,
     *     planning_time_ms: float|null,
     *     shared_hit_blocks: int,
     *     shared_read_blocks: int,
     *     rows_examined: int,
     *     dominant_plan_nodes: list<string>,
     *     plan: array<string, mixed>|list<mixed>
     * }
     */
    public function explain(string $sql, array $bindings = []): array
    {
        if (! self::isReadOnlyQuery($sql)) {
            throw new InvalidArgumentException('EXPLAIN plan collection is only allowed for read-only SELECT or WITH queries.');
        }

        $sanitizedBindings = self::sanitizeBindings($bindings);
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            // Non-postgres fallback for unit/feature tests running on other drivers
            return [
                'sql' => $sql,
                'sanitized_bindings' => $sanitizedBindings,
                'execution_time_ms' => null,
                'planning_time_ms' => null,
                'shared_hit_blocks' => 0,
                'shared_read_blocks' => 0,
                'rows_examined' => 0,
                'dominant_plan_nodes' => ['Fallback Scan'],
                'plan' => ['driver' => $driver, 'note' => 'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) requires PostgreSQL'],
            ];
        }

        $explainSql = "EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) {$sql}";

        try {
            $results = DB::select($explainSql, $sanitizedBindings);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to collect EXPLAIN plan: {$e->getMessage()}", 0, $e);
        }

        if (empty($results)) {
            throw new RuntimeException('PostgreSQL EXPLAIN returned an empty result set.');
        }

        $firstRow = (array) $results[0];
        $rawJson = $firstRow['QUERY PLAN'] ?? reset($firstRow);

        $parsed = is_string($rawJson) ? json_decode($rawJson, true) : (array) $rawJson;
        if (! is_array($parsed) || empty($parsed)) {
            throw new RuntimeException('Failed to parse PostgreSQL EXPLAIN JSON output.');
        }

        $root = $parsed[0] ?? $parsed;
        $planTree = $root['Plan'] ?? [];

        $summary = $this->extractPlanSummary($planTree);

        return [
            'sql' => $sql,
            'sanitized_bindings' => $sanitizedBindings,
            'execution_time_ms' => isset($root['Execution Time']) ? (float) $root['Execution Time'] : null,
            'planning_time_ms' => isset($root['Planning Time']) ? (float) $root['Planning Time'] : null,
            'shared_hit_blocks' => $summary['total_shared_hit'],
            'shared_read_blocks' => $summary['total_shared_read'],
            'rows_examined' => $summary['total_rows_examined'],
            'dominant_plan_nodes' => $summary['dominant_nodes'],
            'plan' => $parsed,
        ];
    }

    /**
     * Checks if a SQL query is read-only (SELECT or WITH without data-modifying CTEs or mutations).
     */
    public static function isReadOnlyQuery(string $sql): bool
    {
        $clean = trim((string) preg_replace('/\/\*.*?\*\/|--.*?(\r?\n|$)/s', '', $sql));
        if ($clean === '') {
            return false;
        }

        $firstWord = strtoupper((string) strtok($clean, " \t\r\n("));
        if (! in_array($firstWord, ['SELECT', 'WITH'], true)) {
            return false;
        }

        $mutationPatterns = [
            '/\bINSERT\s+INTO\b/i',
            '/\bUPDATE\s+[a-zA-Z0-9_"]+\s+SET\b/i',
            '/\bDELETE\s+FROM\b/i',
            '/\bDROP\s+(TABLE|VIEW|INDEX|SCHEMA|DATABASE)\b/i',
            '/\bALTER\s+(TABLE|VIEW|INDEX|SCHEMA)\b/i',
            '/\bTRUNCATE\b/i',
            '/\bGRANT\b/i',
            '/\bREVOKE\b/i',
        ];

        foreach ($mutationPatterns as $pattern) {
            if (preg_match($pattern, $clean)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitizes bindings to redact any secrets, passwords, or authentication credentials.
     *
     * @param  list<mixed>  $bindings
     * @return list<mixed>
     */
    public static function sanitizeBindings(array $bindings): array
    {
        return array_map(function ($val) {
            if (! is_string($val)) {
                return $val;
            }

            if (preg_match('/(password|token|bearer|secret|auth_key)/i', $val)) {
                return '[REDACTED]';
            }

            return $val;
        }, $bindings);
    }

    /**
     * Recursively traverses PostgreSQL plan tree to aggregate buffer hits, reads, and node types.
     *
     * @param  array<string, mixed>  $plan
     * @return array{
     *     dominant_nodes: list<string>,
     *     total_shared_hit: int,
     *     total_shared_read: int,
     *     total_rows_examined: int
     * }
     */
    private function extractPlanSummary(array $plan): array
    {
        $nodes = [];
        $sharedHit = 0;
        $sharedRead = 0;
        $rows = 0;

        $traverse = function (array $node) use (&$traverse, &$nodes, &$sharedHit, &$sharedRead, &$rows): void {
            if (isset($node['Node Type'])) {
                $nodes[] = (string) $node['Node Type'];
            }
            $sharedHit += (int) ($node['Shared Hit Blocks'] ?? 0);
            $sharedRead += (int) ($node['Shared Read Blocks'] ?? 0);
            $rows += (int) ($node['Actual Rows'] ?? 0);

            if (isset($node['Plans']) && is_array($node['Plans'])) {
                foreach ($node['Plans'] as $child) {
                    if (is_array($child)) {
                        $traverse($child);
                    }
                }
            }
        };

        $traverse($plan);

        return [
            'dominant_nodes' => array_values(array_unique($nodes)),
            'total_shared_hit' => $sharedHit,
            'total_shared_read' => $sharedRead,
            'total_rows_examined' => $rows,
        ];
    }
}
