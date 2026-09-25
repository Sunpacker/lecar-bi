<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Infrastructure\Persistence;

use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesPaginatedDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierFilterOptionsDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformancePaginatedDto;
use App\Shared\Infrastructure\Cache\AnalyticsCacheKey;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use App\Shared\Infrastructure\Cache\AnalyticsResultCache;
use App\Shared\Infrastructure\Cache\CanonicalCriteria;

final class CachedSupplierAnalyticsReadModel implements SupplierAnalyticsReadModelInterface
{
    private const DATASET = 'suppliers';

    public function __construct(
        private readonly SupplierAnalyticsReadModelInterface $delegate,
        private readonly AnalyticsResultCache $cache,
        private readonly AnalyticsDatasetVersionStore $versionStore,
    ) {}

    public function getSupplierOverview(string $workspaceId, SupplierOverviewCriteriaDto $criteria): SupplierOverviewDto
    {
        $schemaVersion = (int) config('analytics.schema_version', 1);
        $ttl = (int) config('analytics.ttls.supplier_overview', 120);
        $datasetVersion = $this->versionStore->getVersion($workspaceId, self::DATASET);
        $criteriaHash = CanonicalCriteria::toHash($criteria);

        $cacheKey = AnalyticsCacheKey::compute(
            schemaVersion: $schemaVersion,
            dataset: self::DATASET,
            workspaceId: $workspaceId,
            datasetVersion: $datasetVersion,
            operation: 'supplier_overview',
            criteriaHash: $criteriaHash
        );

        $context = [
            'dataset' => self::DATASET,
            'workspace_id' => $workspaceId,
            'operation' => 'supplier_overview',
        ];

        /** @var SupplierOverviewDto */
        return $this->cache->remember(
            key: $cacheKey,
            ttlSeconds: $ttl,
            callback: fn () => $this->delegate->getSupplierOverview($workspaceId, $criteria),
            sanitizedContext: $context
        );
    }

    public function getFilterOptions(string $workspaceId): SupplierFilterOptionsDto
    {
        $schemaVersion = (int) config('analytics.schema_version', 1);
        $ttl = (int) config('analytics.ttls.supplier_filters', 300);
        $datasetVersion = $this->versionStore->getVersion($workspaceId, self::DATASET);
        $criteriaHash = CanonicalCriteria::toHash([]);

        $cacheKey = AnalyticsCacheKey::compute(
            schemaVersion: $schemaVersion,
            dataset: self::DATASET,
            workspaceId: $workspaceId,
            datasetVersion: $datasetVersion,
            operation: 'supplier_filters',
            criteriaHash: $criteriaHash
        );

        $context = [
            'dataset' => self::DATASET,
            'workspace_id' => $workspaceId,
            'operation' => 'supplier_filters',
        ];

        /** @var SupplierFilterOptionsDto */
        return $this->cache->remember(
            key: $cacheKey,
            ttlSeconds: $ttl,
            callback: fn () => $this->delegate->getFilterOptions($workspaceId),
            sanitizedContext: $context
        );
    }

    /**
     * Non-cached high-cardinality endpoint: directly delegates to PostgreSQL read model.
     */
    public function getSupplierPerformance(string $workspaceId, SupplierPerformanceCriteriaDto $criteria): SupplierPerformancePaginatedDto
    {
        return $this->delegate->getSupplierPerformance($workspaceId, $criteria);
    }

    /**
     * Non-cached high-cardinality endpoint: directly delegates to PostgreSQL read model.
     */
    public function getSupplierDeliveries(string $workspaceId, SupplierDeliveriesCriteriaDto $criteria): SupplierDeliveriesPaginatedDto
    {
        return $this->delegate->getSupplierDeliveries($workspaceId, $criteria);
    }
}
