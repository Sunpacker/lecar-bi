<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class DeliveryStatusBreakdownDto
{
    public function __construct(
        public string $status,
        public int $count,
        public float $sharePercentage,
        public int $quantity,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'count' => $this->count,
            'share_percentage' => $this->sharePercentage,
            'quantity' => $this->quantity,
        ];
    }
}
