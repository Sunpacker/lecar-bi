<?php

namespace App\Modules\DataIngestion\Application\Dtos;

/**
 * @template T
 */
final readonly class PaginatedListDto
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {}
}
