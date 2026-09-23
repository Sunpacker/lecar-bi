<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierPerformancePaginatedDto
{
    /** @param list<SupplierPerformanceItemDto> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (SupplierPerformanceItemDto $item) => $item->toArray(), $this->items),
            'pagination' => [
                'page' => $this->page,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'total_pages' => $this->totalPages,
            ],
        ];
    }
}
