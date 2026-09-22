<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class AbcDistributionDto
{
    public function __construct(
        public string $class,
        public string $label,
        public int $count,
        public float $countShare,
        public float $revenue,
        public float $revenueShare,
    ) {}
}
