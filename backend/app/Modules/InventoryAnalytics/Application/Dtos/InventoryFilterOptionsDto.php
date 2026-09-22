<?php

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class InventoryFilterOptionsDto
{
    /**
     * @param  list<array{id: string, name: string, code: string}>  $warehouses
     * @param  list<array{value: string, label: string}>  $statuses
     */
    public function __construct(
        public array $warehouses,
        public array $statuses,
        public string $latestSnapshotDate,
    ) {}
}
