<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierDeliveryItemDto
{
    public function __construct(
        public string $id,
        public string $orderDate,
        public string $expectedDeliveryDate,
        public ?string $actualDeliveryDate,
        public string $supplierId,
        public string $supplierName,
        public string $productId,
        public string $productName,
        public string $productSku,
        public string $warehouseId,
        public string $warehouseName,
        public int $orderedQuantity,
        public int $receivedQuantity,
        public int $defectQuantity,
        public float $unitPurchaseCost,
        public float $totalPurchaseCost,
        public string $deliveryStatus,
        public int $leadTimeDays,
        public int $delayDays,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_date' => $this->orderDate,
            'expected_delivery_date' => $this->expectedDeliveryDate,
            'actual_delivery_date' => $this->actualDeliveryDate,
            'supplier_id' => $this->supplierId,
            'supplier_name' => $this->supplierName,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
            'warehouse_id' => $this->warehouseId,
            'warehouse_name' => $this->warehouseName,
            'ordered_quantity' => $this->orderedQuantity,
            'received_quantity' => $this->receivedQuantity,
            'defect_quantity' => $this->defectQuantity,
            'unit_purchase_cost' => $this->unitPurchaseCost,
            'total_purchase_cost' => $this->totalPurchaseCost,
            'delivery_status' => $this->deliveryStatus,
            'lead_time_days' => $this->leadTimeDays,
            'delay_days' => $this->delayDays,
        ];
    }
}
