<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class AbcXyzMatrixCellDto
{
    public function __construct(
        public string $code,
        public string $label,
        public string $description,
        public string $recommendation,
        public int $count,
        public float $countShare,
        public float $revenue,
        public float $revenueShare,
        public float $inventoryValue,
        public float $inventoryValueShare,
    ) {}
}
