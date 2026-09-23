<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierTrendPointDto
{
    public function __construct(
        public string $period,
        public int $deliveriesCount,
        public int $onTimeDeliveries,
        public float $totalSpend,
        public float $onTimeRate,
        public float $fulfillmentRate,
        public float $avgLeadTimeDays,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'deliveries_count' => $this->deliveriesCount,
            'on_time_deliveries' => $this->onTimeDeliveries,
            'total_spend' => $this->totalSpend,
            'on_time_rate' => $this->onTimeRate,
            'fulfillment_rate' => $this->fulfillmentRate,
            'avg_lead_time_days' => $this->avgLeadTimeDays,
        ];
    }
}
