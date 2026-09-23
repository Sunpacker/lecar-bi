<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

final class RuleScope
{
    public function __construct(
        private ?string $warehouseId = null,
        private ?string $categoryId = null,
        private ?string $productId = null,
    ) {}

    public function warehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function categoryId(): ?string
    {
        return $this->categoryId;
    }

    public function productId(): ?string
    {
        return $this->productId;
    }

    public function matches(?string $warehouseId, ?string $categoryId, ?string $productId): bool
    {
        if ($this->warehouseId !== null && $this->warehouseId !== $warehouseId) {
            return false;
        }

        if ($this->categoryId !== null && $this->categoryId !== $categoryId) {
            return false;
        }

        if ($this->productId !== null && $this->productId !== $productId) {
            return false;
        }

        return true;
    }
}
