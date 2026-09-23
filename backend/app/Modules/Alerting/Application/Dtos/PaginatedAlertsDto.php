<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Dtos;

final readonly class PaginatedAlertsDto
{
    /**
     * @param  list<AlertDto>  $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
        public int $totalPages,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (AlertDto $item) => $item->toArray(), $this->items),
            'pagination' => [
                'page' => $this->page,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'total_pages' => $this->totalPages,
            ],
        ];
    }
}
