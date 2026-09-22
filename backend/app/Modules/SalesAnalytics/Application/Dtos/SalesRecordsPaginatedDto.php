<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesRecordsPaginatedDto
{
    /**
     * @param array<int, SalesRecordDto> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {}
}
