<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class AbcXyzProductItemsPaginatedDto
{
    /**
     * @param  list<AbcXyzProductItemDto>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {}
}
