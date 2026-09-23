<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

final class AlertContext
{
    public function __construct(
        private string $target,
        private ?string $warehouseId = null,
        private ?string $warehouseName = null,
        private ?string $productId = null,
        private ?string $productName = null,
        private ?string $productSku = null,
        private ?float $currentValue = null,
        private ?float $thresholdValue = null,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function warehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function warehouseName(): ?string
    {
        return $this->warehouseName;
    }

    public function productId(): ?string
    {
        return $this->productId;
    }

    public function productName(): ?string
    {
        return $this->productName;
    }

    public function productSku(): ?string
    {
        return $this->productSku;
    }

    public function currentValue(): ?float
    {
        return $this->currentValue;
    }

    public function thresholdValue(): ?float
    {
        return $this->thresholdValue;
    }

    public function withCurrentValue(float $newValue): self
    {
        return new self(
            target: $this->target,
            warehouseId: $this->warehouseId,
            warehouseName: $this->warehouseName,
            productId: $this->productId,
            productName: $this->productName,
            productSku: $this->productSku,
            currentValue: $newValue,
            thresholdValue: $this->thresholdValue,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'warehouse_id' => $this->warehouseId,
            'warehouse_name' => $this->warehouseName,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
            'current_value' => $this->currentValue,
            'threshold_value' => $this->thresholdValue,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            target: (string) ($data['target'] ?? 'inventory'),
            warehouseId: isset($data['warehouse_id']) ? (string) $data['warehouse_id'] : null,
            warehouseName: isset($data['warehouse_name']) ? (string) $data['warehouse_name'] : null,
            productId: isset($data['product_id']) ? (string) $data['product_id'] : null,
            productName: isset($data['product_name']) ? (string) $data['product_name'] : null,
            productSku: isset($data['product_sku']) ? (string) $data['product_sku'] : null,
            currentValue: isset($data['current_value']) ? (float) $data['current_value'] : null,
            thresholdValue: isset($data['threshold_value']) ? (float) $data['threshold_value'] : null,
        );
    }
}
