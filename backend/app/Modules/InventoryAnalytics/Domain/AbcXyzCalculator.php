<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain;

final class AbcXyzCalculator
{
    /**
     * Calculates coefficient of variation (CV = standard_deviation / mean).
     *
     * @param  list<float|int>  $periodVolumes
     */
    public static function calculateVariation(array $periodVolumes): ?float
    {
        $n = count($periodVolumes);
        if ($n === 0) {
            return null;
        }

        $sum = array_sum($periodVolumes);
        $mean = $sum / $n;

        if ($mean <= 0.0) {
            return null;
        }

        if ($n === 1) {
            return 0.0;
        }

        $variance = 0.0;
        foreach ($periodVolumes as $volume) {
            $variance += pow((float) $volume - $mean, 2);
        }

        $standardDeviation = sqrt($variance / ($n - 1));

        return round($standardDeviation / $mean, 4);
    }

    /**
     * Calculates standard deviation for period volumes.
     *
     * @param  list<float|int>  $periodVolumes
     */
    public static function calculateStandardDeviation(array $periodVolumes): float
    {
        $n = count($periodVolumes);
        if ($n <= 1) {
            return 0.0;
        }

        $mean = array_sum($periodVolumes) / $n;
        $variance = 0.0;
        foreach ($periodVolumes as $volume) {
            $variance += pow((float) $volume - $mean, 2);
        }

        return round(sqrt($variance / ($n - 1)), 2);
    }

    /**
     * Classifies ABC based on cumulative revenue share and product revenue.
     */
    public static function classifyAbc(float $cumulativeShare, float $itemRevenue): AbcClass
    {
        if ($itemRevenue <= 0.0) {
            return AbcClass::C;
        }

        if ($cumulativeShare <= 0.80001) {
            return AbcClass::A;
        }

        if ($cumulativeShare <= 0.95001) {
            return AbcClass::B;
        }

        return AbcClass::C;
    }

    /**
     * Classifies XYZ based on CV and units sold.
     */
    public static function classifyXyz(?float $cv, int|float $totalUnitsSold): XyzClass
    {
        if ($cv === null || $totalUnitsSold <= 0) {
            return XyzClass::Z;
        }

        if ($cv <= 0.15001) {
            return XyzClass::X;
        }

        if ($cv <= 0.35001) {
            return XyzClass::Y;
        }

        return XyzClass::Z;
    }

    /**
     * Combines ABC and XYZ classes into group.
     */
    public static function classifyGroup(AbcClass $abc, XyzClass $xyz): AbcXyzGroup
    {
        return AbcXyzGroup::from($abc->value.$xyz->value);
    }

    /**
     * Analyzes raw product records and returns comprehensive ABC/XYZ metrics.
     *
     * @param  list<array<string, mixed>>  $rawProducts
     * @return array{
     *     total_products: int,
     *     total_revenue: float,
     *     total_inventory_value: float,
     *     items: list<array<string, mixed>>,
     *     matrix: list<array<string, mixed>>,
     *     abc_distribution: list<array<string, mixed>>,
     *     xyz_distribution: list<array<string, mixed>>
     * }
     */
    public static function analyze(array $rawProducts): array
    {
        $totalProducts = count($rawProducts);
        if ($totalProducts === 0) {
            return [
                'total_products' => 0,
                'total_revenue' => 0.0,
                'total_inventory_value' => 0.0,
                'items' => [],
                'matrix' => self::emptyMatrix(),
                'abc_distribution' => self::emptyAbcDistribution(),
                'xyz_distribution' => self::emptyXyzDistribution(),
            ];
        }

        // Sort products by revenue DESC, secondary tie-break by product_id ASC
        usort($rawProducts, function (array $a, array $b): int {
            $revA = (float) ($a['revenue'] ?? 0.0);
            $revB = (float) ($b['revenue'] ?? 0.0);

            if ($revA !== $revB) {
                return $revA > $revB ? -1 : 1;
            }

            return strcmp((string) ($a['product_id'] ?? ''), (string) ($b['product_id'] ?? ''));
        });

        $totalRevenue = 0.0;
        $totalInventoryValue = 0.0;
        foreach ($rawProducts as $p) {
            $totalRevenue += max(0.0, (float) ($p['revenue'] ?? 0.0));
            $totalInventoryValue += max(0.0, (float) ($p['inventory_value'] ?? 0.0));
        }
        $totalRevenue = round($totalRevenue, 2);
        $totalInventoryValue = round($totalInventoryValue, 2);

        $runningRevenue = 0.0;
        $analyzedItems = [];

        // Matrix aggregation buckets
        $matrixBuckets = [];
        foreach (AbcXyzGroup::cases() as $group) {
            $matrixBuckets[$group->value] = [
                'count' => 0,
                'revenue' => 0.0,
                'inventory_value' => 0.0,
            ];
        }

        // Distribution buckets
        $abcBuckets = [
            'A' => ['count' => 0, 'revenue' => 0.0],
            'B' => ['count' => 0, 'revenue' => 0.0],
            'C' => ['count' => 0, 'revenue' => 0.0],
        ];

        $xyzBuckets = [
            'X' => ['count' => 0, 'revenue' => 0.0],
            'Y' => ['count' => 0, 'revenue' => 0.0],
            'Z' => ['count' => 0, 'revenue' => 0.0],
        ];

        foreach ($rawProducts as $p) {
            $rev = max(0.0, (float) ($p['revenue'] ?? 0.0));
            $units = max(0, (int) ($p['units_sold'] ?? 0));
            $invVal = max(0.0, (float) ($p['inventory_value'] ?? 0.0));
            $periodSales = array_map('floatval', (array) ($p['period_sales'] ?? []));

            $revShare = $totalRevenue > 0.0 ? round($rev / $totalRevenue, 4) : 0.0;
            $runningRevenue += $rev;
            $cumShare = $totalRevenue > 0.0 ? round(min(1.0, $runningRevenue / $totalRevenue), 4) : 0.0;

            $abcClass = self::classifyAbc($cumShare, $rev);
            $cv = self::calculateVariation($periodSales);
            $xyzClass = self::classifyXyz($cv, $units);
            $group = self::classifyGroup($abcClass, $xyzClass);

            $periodCount = count($periodSales);
            $avgSales = $periodCount > 0 ? round(array_sum($periodSales) / $periodCount, 2) : 0.0;
            $stdDev = self::calculateStandardDeviation($periodSales);

            $analyzedItems[] = [
                'id' => (string) ($p['id'] ?? $p['product_id']),
                'product_id' => (string) $p['product_id'],
                'product_name' => (string) ($p['product_name'] ?? ''),
                'product_sku' => (string) ($p['product_sku'] ?? ''),
                'category_id' => (string) ($p['category_id'] ?? ''),
                'category_name' => (string) ($p['category_name'] ?? ''),
                'brand_name' => (string) ($p['brand_name'] ?? ''),
                'supplier_id' => isset($p['supplier_id']) ? (string) $p['supplier_id'] : null,
                'supplier_name' => isset($p['supplier_name']) ? (string) $p['supplier_name'] : null,
                'total_revenue' => $rev,
                'total_units_sold' => $units,
                'revenue_share' => $revShare,
                'cumulative_revenue_share' => $cumShare,
                'abc_class' => $abcClass->value,
                'period_sales' => $periodSales,
                'average_sales' => $avgSales,
                'standard_deviation' => $stdDev,
                'coefficient_of_variation' => $cv,
                'xyz_class' => $xyzClass->value,
                'abc_xyz_group' => $group->value,
                'current_stock' => (int) ($p['current_stock'] ?? 0),
                'inventory_value' => $invVal,
            ];

            // Aggregate into matrix buckets
            $matrixBuckets[$group->value]['count']++;
            $matrixBuckets[$group->value]['revenue'] += $rev;
            $matrixBuckets[$group->value]['inventory_value'] += $invVal;

            // Aggregate into distribution buckets
            $abcBuckets[$abcClass->value]['count']++;
            $abcBuckets[$abcClass->value]['revenue'] += $rev;

            $xyzBuckets[$xyzClass->value]['count']++;
            $xyzBuckets[$xyzClass->value]['revenue'] += $rev;
        }

        // Build matrix cells
        $matrix = [];
        foreach (AbcXyzGroup::cases() as $g) {
            $b = $matrixBuckets[$g->value];
            $count = $b['count'];
            $rev = round($b['revenue'], 2);
            $val = round($b['inventory_value'], 2);

            $matrix[] = [
                'code' => $g->value,
                'label' => $g->label(),
                'description' => $g->description(),
                'recommendation' => $g->recommendation(),
                'count' => $count,
                'count_share' => round($count / $totalProducts, 4),
                'revenue' => $rev,
                'revenue_share' => $totalRevenue > 0.0 ? round($rev / $totalRevenue, 4) : 0.0,
                'inventory_value' => $val,
                'inventory_value_share' => $totalInventoryValue > 0.0 ? round($val / $totalInventoryValue, 4) : 0.0,
            ];
        }

        // Build ABC distribution
        $abcDist = [];
        foreach (AbcClass::cases() as $abc) {
            $b = $abcBuckets[$abc->value];
            $count = $b['count'];
            $rev = round($b['revenue'], 2);

            $abcDist[] = [
                'class' => $abc->value,
                'label' => $abc->label(),
                'count' => $count,
                'count_share' => round($count / $totalProducts, 4),
                'revenue' => $rev,
                'revenue_share' => $totalRevenue > 0.0 ? round($rev / $totalRevenue, 4) : 0.0,
            ];
        }

        // Build XYZ distribution
        $xyzDist = [];
        foreach (XyzClass::cases() as $xyz) {
            $b = $xyzBuckets[$xyz->value];
            $count = $b['count'];
            $rev = round($b['revenue'], 2);

            $xyzDist[] = [
                'class' => $xyz->value,
                'label' => $xyz->label(),
                'count' => $count,
                'count_share' => round($count / $totalProducts, 4),
                'revenue' => $rev,
                'revenue_share' => $totalRevenue > 0.0 ? round($rev / $totalRevenue, 4) : 0.0,
            ];
        }

        return [
            'total_products' => $totalProducts,
            'total_revenue' => $totalRevenue,
            'total_inventory_value' => $totalInventoryValue,
            'items' => $analyzedItems,
            'matrix' => $matrix,
            'abc_distribution' => $abcDist,
            'xyz_distribution' => $xyzDist,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function emptyMatrix(): array
    {
        $matrix = [];
        foreach (AbcXyzGroup::cases() as $g) {
            $matrix[] = [
                'code' => $g->value,
                'label' => $g->label(),
                'description' => $g->description(),
                'recommendation' => $g->recommendation(),
                'count' => 0,
                'count_share' => 0.0,
                'revenue' => 0.0,
                'revenue_share' => 0.0,
                'inventory_value' => 0.0,
                'inventory_value_share' => 0.0,
            ];
        }

        return $matrix;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function emptyAbcDistribution(): array
    {
        return array_map(fn (AbcClass $abc) => [
            'class' => $abc->value,
            'label' => $abc->label(),
            'count' => 0,
            'count_share' => 0.0,
            'revenue' => 0.0,
            'revenue_share' => 0.0,
        ], AbcClass::cases());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function emptyXyzDistribution(): array
    {
        return array_map(fn (XyzClass $xyz) => [
            'class' => $xyz->value,
            'label' => $xyz->label(),
            'count' => 0,
            'count_share' => 0.0,
            'revenue' => 0.0,
            'revenue_share' => 0.0,
        ], XyzClass::cases());
    }
}
