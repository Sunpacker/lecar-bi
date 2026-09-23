<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierFilterOptionsDto
{
    /**
     * @param  list<array{id: string, name: string}>  $suppliers
     * @param  list<array{id: string, name: string}>  $warehouses
     * @param  list<array{value: string, label: string}>  $statuses
     */
    public function __construct(
        public array $suppliers,
        public array $warehouses,
        public array $statuses,
        public string $minDate,
        public string $maxDate,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'suppliers' => $this->suppliers,
            'warehouses' => $this->warehouses,
            'statuses' => $this->statuses,
            'min_date' => $this->minDate,
            'max_date' => $this->maxDate,
        ];
    }
}
