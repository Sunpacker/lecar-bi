<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierPerformanceItemDto
{
    public function __construct(
        public string $supplierId,
        public string $supplierName,
        public int $totalDeliveries,
        public int $onTimeDeliveries,
        public int $delayedDeliveries,
        public int $partialDeliveries,
        public float $totalSpend,
        public int $orderedQuantity,
        public int $receivedQuantity,
        public int $defectQuantity,
        public float $onTimeRate,
        public float $delayRate,
        public float $fulfillmentRate,
        public float $defectRate,
        public float $avgLeadTimeDays,
        public float $avgDelayDays,
        public float $reliabilityScore,
        public string $reliabilityTier,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'supplier_id' => $this->supplierId,
            'supplier_name' => $this->supplierName,
            'total_deliveries' => $this->totalDeliveries,
            'on_time_deliveries' => $this->onTimeDeliveries,
            'delayed_deliveries' => $this->delayedDeliveries,
            'partial_deliveries' => $this->partialDeliveries,
            'total_spend' => $this->totalSpend,
            'ordered_quantity' => $this->orderedQuantity,
            'received_quantity' => $this->receivedQuantity,
            'defect_quantity' => $this->defectQuantity,
            'on_time_rate' => $this->onTimeRate,
            'delay_rate' => $this->delayRate,
            'fulfillment_rate' => $this->fulfillmentRate,
            'defect_rate' => $this->defectRate,
            'avg_lead_time_days' => $this->avgLeadTimeDays,
            'avg_delay_days' => $this->avgDelayDays,
            'reliability_score' => $this->reliabilityScore,
            'reliability_tier' => $this->reliabilityTier,
        ];
    }
}
