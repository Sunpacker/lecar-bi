<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cache;

final class AnalyticsCacheKey
{
    public const PREFIX = 'analytics';

    /**
     * Computes the standardized analytics cache key:
     * analytics:{schema_version}:{dataset}:{workspace_id}:{dataset_version}:{operation}:{criteria_hash}
     */
    public static function compute(
        int $schemaVersion,
        string $dataset,
        string $workspaceId,
        int $datasetVersion,
        string $operation,
        string $criteriaHash
    ): string {
        return sprintf(
            '%s:%d:%s:%s:%d:%s:%s',
            self::PREFIX,
            $schemaVersion,
            strtolower(trim($dataset)),
            trim($workspaceId),
            $datasetVersion,
            strtolower(trim($operation)),
            trim($criteriaHash)
        );
    }
}
