<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class InventoryFilterOptionsDto
{
    /**
     * @param  list<array{id: string, name: string, code: string}>  $warehouses
     * @param  list<array{value: string, label: string}>  $statuses
     * @param  list<array{id: string, name: string, code: string}>  $categories
     * @param  list<array{id: string, name: string}>  $suppliers
     */
    public function __construct(
        public array $warehouses,
        public array $statuses,
        public string $latestSnapshotDate,
        public array $categories = [],
        public array $suppliers = [],
    ) {}
}
