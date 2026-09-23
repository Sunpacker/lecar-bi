<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierSummaryDto
{
    public function __construct(
        public int $totalDeliveries,
        public int $onTimeDeliveries,
        public int $delayedDeliveries,
        public int $partialDeliveries,
        public float $totalSpend,
        public int $totalOrderedQuantity,
        public int $totalReceivedQuantity,
        public int $totalDefectQuantity,
        public float $onTimeRate,
        public float $delayRate,
        public float $fulfillmentRate,
        public float $defectRate,
        public float $averageLeadTimeDays,
        public float $averageDelayDays,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total_deliveries' => $this->totalDeliveries,
            'on_time_deliveries' => $this->onTimeDeliveries,
            'delayed_deliveries' => $this->delayedDeliveries,
            'partial_deliveries' => $this->partialDeliveries,
            'total_spend' => $this->totalSpend,
            'total_ordered_quantity' => $this->totalOrderedQuantity,
            'total_received_quantity' => $this->totalReceivedQuantity,
            'total_defect_quantity' => $this->totalDefectQuantity,
            'on_time_rate' => $this->onTimeRate,
            'delay_rate' => $this->delayRate,
            'fulfillment_rate' => $this->fulfillmentRate,
            'defect_rate' => $this->defectRate,
            'average_lead_time_days' => $this->averageLeadTimeDays,
            'average_delay_days' => $this->averageDelayDays,
        ];
    }
}
