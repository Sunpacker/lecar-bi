<?php

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class InventoryItemsPaginatedDto
{
    /**
     * @param  list<InventoryItemDto>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {}
}
