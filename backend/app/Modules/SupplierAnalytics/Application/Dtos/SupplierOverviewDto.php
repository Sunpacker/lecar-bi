<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierOverviewDto
{
    /**
     * @param  list<DeliveryStatusBreakdownDto>  $statusBreakdown
     * @param  list<SupplierTrendPointDto>  $trends
     * @param  list<SupplierPerformanceItemDto>  $topSuppliers
     */
    public function __construct(
        public SupplierSummaryDto $summary,
        public array $statusBreakdown,
        public array $trends,
        public array $topSuppliers,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary->toArray(),
            'status_breakdown' => array_map(fn (DeliveryStatusBreakdownDto $item) => $item->toArray(), $this->statusBreakdown),
            'trends' => array_map(fn (SupplierTrendPointDto $item) => $item->toArray(), $this->trends),
            'top_suppliers' => array_map(fn (SupplierPerformanceItemDto $item) => $item->toArray(), $this->topSuppliers),
        ];
    }
}
