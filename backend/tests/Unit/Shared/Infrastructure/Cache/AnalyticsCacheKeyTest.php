<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Cache;

use App\Shared\Infrastructure\Cache\AnalyticsCacheKey;
use App\Shared\Infrastructure\Cache\CanonicalCriteria;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnalyticsCacheKeyTest extends TestCase
{
    #[Test]
    public function it_computes_standardized_cache_key(): void
    {
        $key = AnalyticsCacheKey::compute(
            schemaVersion: 1,
            dataset: 'sales',
            workspaceId: 'ws-100',
            datasetVersion: 42,
            operation: 'sales_overview',
            criteriaHash: 'abc123hash'
        );

        self::assertSame('analytics:1:sales:ws-100:42:sales_overview:abc123hash', $key);
    }

    #[Test]
    public function it_normalizes_whitespace_and_case_in_keys(): void
    {
        $key = AnalyticsCacheKey::compute(
            schemaVersion: 2,
            dataset: ' INVENTORY ',
            workspaceId: ' perf-ws-1 ',
            datasetVersion: 5,
            operation: ' INVENTORY_SUMMARY ',
            criteriaHash: 'def456'
        );

        self::assertSame('analytics:2:inventory:perf-ws-1:5:inventory_summary:def456', $key);
    }

    #[Test]
    public function canonical_criteria_produces_identical_hash_regardless_of_key_order(): void
    {
        $criteriaA = [
            'date_to' => '2026-12-31',
            'date_from' => '2026-01-01',
            'warehouse_id' => 'wh-1',
        ];

        $criteriaB = [
            'warehouse_id' => 'wh-1',
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ];

        self::assertSame(
            CanonicalCriteria::toHash($criteriaA),
            CanonicalCriteria::toHash($criteriaB)
        );
    }

    #[Test]
    public function canonical_criteria_handles_nested_objects_and_arrays(): void
    {
        $objA = (object) [
            'b' => 2,
            'a' => (object) ['y' => 'yes', 'x' => 'no'],
        ];

        $objB = (object) [
            'a' => (object) ['x' => 'no', 'y' => 'yes'],
            'b' => 2,
        ];

        self::assertSame(
            CanonicalCriteria::toHash($objA),
            CanonicalCriteria::toHash($objB)
        );
    }

    #[Test]
    public function different_criteria_produce_different_hashes(): void
    {
        $hash1 = CanonicalCriteria::toHash(['date_from' => '2026-01-01']);
        $hash2 = CanonicalCriteria::toHash(['date_from' => '2026-01-02']);

        self::assertNotSame($hash1, $hash2);
    }
}
