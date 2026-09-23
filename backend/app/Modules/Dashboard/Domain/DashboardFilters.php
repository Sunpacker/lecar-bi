<?php

namespace App\Modules\Dashboard\Domain;

use App\Modules\Dashboard\Domain\Exceptions\InvalidFilterException;

final readonly class DashboardFilters
{
    public function __construct(
        public ?string $dateRange = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
        public ?string $warehouseId = null,
        public ?string $stockHealth = null,
    ) {
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new InvalidFilterException("date_from ({$dateFrom}) cannot be later than date_to ({$dateTo}).");
        }
    }

    public function forSalesDataset(): self
    {
        return new self(
            dateRange: $this->dateRange,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            categoryId: $this->categoryId,
            regionId: $this->regionId,
            warehouseId: null,
            stockHealth: null,
        );
    }

    public function forInventoryDataset(): self
    {
        return new self(
            dateRange: $this->dateRange,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            categoryId: $this->categoryId,
            regionId: null,
            warehouseId: $this->warehouseId,
            stockHealth: $this->stockHealth,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'date_range' => $this->dateRange,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'category_id' => $this->categoryId,
            'region_id' => $this->regionId,
            'warehouse_id' => $this->warehouseId,
            'stock_health' => $this->stockHealth,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dateRange: isset($data['date_range']) && is_string($data['date_range']) ? $data['date_range'] : null,
            dateFrom: isset($data['date_from']) && is_string($data['date_from']) ? $data['date_from'] : null,
            dateTo: isset($data['date_to']) && is_string($data['date_to']) ? $data['date_to'] : null,
            categoryId: isset($data['category_id']) && is_string($data['category_id']) ? $data['category_id'] : null,
            regionId: isset($data['region_id']) && is_string($data['region_id']) ? $data['region_id'] : null,
            warehouseId: isset($data['warehouse_id']) && is_string($data['warehouse_id']) ? $data['warehouse_id'] : null,
            stockHealth: isset($data['stock_health']) && is_string($data['stock_health']) ? $data['stock_health'] : null,
        );
    }
}
