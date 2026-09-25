<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Shared\Infrastructure\Cache\AnalyticsCacheKey;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use App\Shared\Infrastructure\Cache\AnalyticsResultCache;
use App\Shared\Infrastructure\Cache\CanonicalCriteria;

final class CachedInventoryAnalyticsReadModel implements InventoryAnalyticsReadModelInterface
{
    private const DATASET = 'inventory';

    public function __construct(
        private readonly InventoryAnalyticsReadModelInterface $delegate,
        private readonly AnalyticsResultCache $cache,
        private readonly AnalyticsDatasetVersionStore $versionStore,
    ) {}

    public function getInventorySummary(string $workspaceId, InventorySummaryCriteriaDto $criteria): InventorySummaryDto
    {
        $schemaVersion = (int) config('analytics.schema_version', 1);
        $ttl = (int) config('analytics.ttls.inventory_summary', 120);
        $datasetVersion = $this->versionStore->getVersion($workspaceId, self::DATASET);
        $criteriaHash = CanonicalCriteria::toHash($criteria);

        $cacheKey = AnalyticsCacheKey::compute(
            schemaVersion: $schemaVersion,
            dataset: self::DATASET,
            workspaceId: $workspaceId,
            datasetVersion: $datasetVersion,
            operation: 'inventory_summary',
            criteriaHash: $criteriaHash
        );

        $context = [
            'dataset' => self::DATASET,
            'workspace_id' => $workspaceId,
            'operation' => 'inventory_summary',
        ];

        /** @var InventorySummaryDto */
        return $this->cache->remember(
            key: $cacheKey,
            ttlSeconds: $ttl,
            callback: fn () => $this->delegate->getInventorySummary($workspaceId, $criteria),
            sanitizedContext: $context
        );
    }

    public function getFilterOptions(string $workspaceId): InventoryFilterOptionsDto
    {
        $schemaVersion = (int) config('analytics.schema_version', 1);
        $ttl = (int) config('analytics.ttls.inventory_filters', 300);
        $datasetVersion = $this->versionStore->getVersion($workspaceId, self::DATASET);
        $criteriaHash = CanonicalCriteria::toHash([]);

        $cacheKey = AnalyticsCacheKey::compute(
            schemaVersion: $schemaVersion,
            dataset: self::DATASET,
            workspaceId: $workspaceId,
            datasetVersion: $datasetVersion,
            operation: 'inventory_filters',
            criteriaHash: $criteriaHash
        );

        $context = [
            'dataset' => self::DATASET,
            'workspace_id' => $workspaceId,
            'operation' => 'inventory_filters',
        ];

        /** @var InventoryFilterOptionsDto */
        return $this->cache->remember(
            key: $cacheKey,
            ttlSeconds: $ttl,
            callback: fn () => $this->delegate->getFilterOptions($workspaceId),
            sanitizedContext: $context
        );
    }

    public function getAbcXyzSummary(string $workspaceId, AbcXyzSummaryCriteriaDto $criteria): AbcXyzSummaryDto
    {
        $schemaVersion = (int) config('analytics.schema_version', 1);
        $ttl = (int) config('analytics.ttls.abc_xyz_summary', 120);
        $datasetVersion = $this->versionStore->getVersion($workspaceId, self::DATASET);
        $criteriaHash = CanonicalCriteria::toHash($criteria);

        $cacheKey = AnalyticsCacheKey::compute(
            schemaVersion: $schemaVersion,
            dataset: self::DATASET,
            workspaceId: $workspaceId,
            datasetVersion: $datasetVersion,
            operation: 'abc_xyz_summary',
            criteriaHash: $criteriaHash
        );

        $context = [
            'dataset' => self::DATASET,
            'workspace_id' => $workspaceId,
            'operation' => 'abc_xyz_summary',
        ];

        /** @var AbcXyzSummaryDto */
        return $this->cache->remember(
            key: $cacheKey,
            ttlSeconds: $ttl,
            callback: fn () => $this->delegate->getAbcXyzSummary($workspaceId, $criteria),
            sanitizedContext: $context
        );
    }

    /**
     * Non-cached high-cardinality endpoint: directly delegates to PostgreSQL read model.
     */
    public function getInventoryItems(string $workspaceId, InventoryItemsCriteriaDto $criteria): InventoryItemsPaginatedDto
    {
        return $this->delegate->getInventoryItems($workspaceId, $criteria);
    }

    /**
     * Non-cached high-cardinality endpoint: directly delegates to PostgreSQL read model.
     */
    public function getAbcXyzItems(string $workspaceId, AbcXyzItemsCriteriaDto $criteria): AbcXyzProductItemsPaginatedDto
    {
        return $this->delegate->getAbcXyzItems($workspaceId, $criteria);
    }
}
