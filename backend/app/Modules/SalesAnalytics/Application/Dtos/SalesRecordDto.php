<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesRecordDto
{
    public function __construct(
        public string $id,
        public string $orderId,
        public string $orderNumber,
        public string $orderDate,
        public string $productId,
        public string $productName,
        public string $productSku,
        public string $categoryId,
        public string $categoryName,
        public string $regionId,
        public string $regionName,
        public string $brandName,
        public int $quantity,
        public float $unitPrice,
        public float $totalPrice,
        public float $grossProfit,
        public string $status,
    ) {}
}
