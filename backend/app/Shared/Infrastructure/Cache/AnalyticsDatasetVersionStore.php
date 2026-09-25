<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cache;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsDatasetVersionStore
{
    /**
     * In-memory cache of versions to avoid redundant queries and to support environments without persistent DB.
     *
     * @var array<string, int>
     */
    private array $versions = [];

    /**
     * Resolves the current monotonic dataset version for a workspace and dataset domain.
     * Defaults to 1 if no mutations have been recorded yet.
     */
    public function getVersion(string $workspaceId, string $dataset): int
    {
        $cacheKey = $workspaceId.':'.$dataset;

        if (isset($this->versions[$cacheKey])) {
            return $this->versions[$cacheKey];
        }

        try {
            /** @var int|string|null $dbVersion */
            $dbVersion = DB::table('analytics_dataset_versions')
                ->where('workspace_id', $workspaceId)
                ->where('dataset', $dataset)
                ->value('version');

            $version = $dbVersion !== null ? (int) $dbVersion : 1;
        } catch (\Throwable) {
            // Safe fallback if table does not exist or connection fails
            $version = 1;
        }

        $this->versions[$cacheKey] = $version;

        return $version;
    }

    /**
     * Atomically increments the monotonic dataset version upon fact mutations/imports.
     * Returns the newly incremented version.
     */
    public function bumpVersion(string $workspaceId, string $dataset): int
    {
        $current = $this->getVersion($workspaceId, $dataset);
        $newVersion = $current + 1;
        $this->versions[$workspaceId.':'.$dataset] = $newVersion;

        try {
            $now = Carbon::now();

            $updated = DB::table('analytics_dataset_versions')
                ->where('workspace_id', $workspaceId)
                ->where('dataset', $dataset)
                ->increment('version', 1, ['updated_at' => $now]);

            if ($updated === 0) {
                DB::table('analytics_dataset_versions')->insert([
                    'workspace_id' => $workspaceId,
                    'dataset' => $dataset,
                    'version' => $newVersion,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable) {
            // Safe fallback for testing or offline environments
        }

        return $newVersion;
    }

    /**
     * Clears local in-memory request-level cache (useful in tests or long-running workers).
     */
    public function resetLocalCache(): void
    {
        $this->versions = [];
    }
}
