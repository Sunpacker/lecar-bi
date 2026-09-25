<?php

declare(strict_types=1);

namespace App\Modules\SalesAnalytics\Infrastructure\Persistence;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Shared\Infrastructure\Cache\AnalyticsCacheKey;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use App\Shared\Infrastructure\Cache\AnalyticsResultCache;
use App\Shared\Infrastructure\Cache\CanonicalCriteria;

final class CachedSalesAnalyticsReadModel implements SalesAnalyticsReadModelInterface
{
    private const DATASET = 'sales';

    public function __construct(
        private readonly SalesAnalyticsReadModelInterface $delegate,
        private readonly AnalyticsResultCache $cache,
        private readonly AnalyticsDatasetVersionStore $versionStore,
    ) {}

    public function getSalesOverview(string $workspaceId, SalesFilterCriteriaDto $criteria): SalesOverviewDto
    {
        $schemaVersion = (int) config('analytics.schema_version', 1);
        $ttl = (int) config('analytics.ttls.sales_overview', 120);
        $datasetVersion = $this->versionStore->getVersion($workspaceId, self::DATASET);
        $criteriaHash = CanonicalCriteria::toHash($criteria);

        $cacheKey = AnalyticsCacheKey::compute(
            schemaVersion: $schemaVersion,
            dataset: self::DATASET,
            workspaceId: $workspaceId,
            datasetVersion: $datasetVersion,
            operation: 'sales_overview',
            criteriaHash: $criteriaHash
        );

        $context = [
            'dataset' => self::DATASET,
            'workspace_id' => $workspaceId,
            'operation' => 'sales_overview',
        ];

        /** @var SalesOverviewDto */
        return $this->cache->remember(
            key: $cacheKey,
            ttlSeconds: $ttl,
            callback: fn () => $this->delegate->getSalesOverview($workspaceId, $criteria),
            sanitizedContext: $context
        );
    }

    public function getFilterOptions(string $workspaceId): SalesFilterOptionsDto
    {
        $schemaVersion = (int) config('analytics.schema_version', 1);
        $ttl = (int) config('analytics.ttls.sales_filters', 300);
        $datasetVersion = $this->versionStore->getVersion($workspaceId, self::DATASET);
        $criteriaHash = CanonicalCriteria::toHash([]);

        $cacheKey = AnalyticsCacheKey::compute(
            schemaVersion: $schemaVersion,
            dataset: self::DATASET,
            workspaceId: $workspaceId,
            datasetVersion: $datasetVersion,
            operation: 'sales_filters',
            criteriaHash: $criteriaHash
        );

        $context = [
            'dataset' => self::DATASET,
            'workspace_id' => $workspaceId,
            'operation' => 'sales_filters',
        ];

        /** @var SalesFilterOptionsDto */
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
    public function getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto
    {
        return $this->delegate->getSalesRecords($workspaceId, $criteria);
    }
}
