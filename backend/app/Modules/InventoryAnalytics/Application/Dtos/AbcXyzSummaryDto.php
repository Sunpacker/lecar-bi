<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class AbcXyzSummaryDto
{
    /**
     * @param  list<AbcXyzMatrixCellDto>  $matrix
     * @param  list<AbcDistributionDto>  $abcDistribution
     * @param  list<XyzDistributionDto>  $xyzDistribution
     */
    public function __construct(
        public int $totalProducts,
        public float $totalRevenue,
        public float $totalInventoryValue,
        public array $matrix,
        public array $abcDistribution,
        public array $xyzDistribution,
        public int $periodDays,
        public string $startDate,
        public string $endDate,
    ) {}
}
