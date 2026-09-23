<?php

namespace App\Modules\DataIngestion\Infrastructure\Validators;

use App\Modules\DataIngestion\Domain\RowError;

/**
 * Validates a Sales dataset row.
 *
 * Fields: order_number, order_date, channel_code, region_code,
 *         warehouse_code, sku, quantity (>0), unit_price (>=0),
 *         unit_cost (>=0), order_status
 */
final class SalesRowValidator implements RowValidatorInterface
{
    /**
     * @param  array<string, string>  $row
     * @return list<RowError>
     */
    public function validate(array $row, int $rowNumber): array
    {
        $errors = [];

        // Required non-empty string fields
        foreach (['order_number', 'channel_code', 'region_code', 'warehouse_code', 'sku', 'order_status'] as $field) {
            if (! $this->isNonEmpty($row[$field] ?? '')) {
                $errors[] = new RowError(
                    rowNumber: $rowNumber,
                    field: $field,
                    value: $row[$field] ?? null,
                    message: "Field '{$field}' is required and must not be empty."
                );
            }
        }

        // Date field
        $orderDate = $row['order_date'] ?? '';
        if (! $this->isValidDate($orderDate)) {
            $errors[] = new RowError(
                rowNumber: $rowNumber,
                field: 'order_date',
                value: $orderDate,
                message: "Field 'order_date' must be a valid date in YYYY-MM-DD format."
            );
        }

        // quantity > 0
        $quantity = $row['quantity'] ?? '';
        if (! $this->isPositiveInteger($quantity)) {
            $errors[] = new RowError(
                rowNumber: $rowNumber,
                field: 'quantity',
                value: $quantity,
                message: "Field 'quantity' must be an integer greater than 0."
            );
        }

        // unit_price >= 0
        $unitPrice = $row['unit_price'] ?? '';
        if (! $this->isNonNegativeNumeric($unitPrice)) {
            $errors[] = new RowError(
                rowNumber: $rowNumber,
                field: 'unit_price',
                value: $unitPrice,
                message: "Field 'unit_price' must be a numeric value >= 0."
            );
        }

        // unit_cost >= 0
        $unitCost = $row['unit_cost'] ?? '';
        if (! $this->isNonNegativeNumeric($unitCost)) {
            $errors[] = new RowError(
                rowNumber: $rowNumber,
                field: 'unit_cost',
                value: $unitCost,
                message: "Field 'unit_cost' must be a numeric value >= 0."
            );
        }

        return $errors;
    }

    private function isNonEmpty(string $value): bool
    {
        return trim($value) !== '';
    }

    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        return $date !== false && $date->format('Y-m-d') === trim($value);
    }

    private function isPositiveInteger(string $value): bool
    {
        $trimmed = trim($value);
        if (! is_numeric($trimmed)) {
            return false;
        }

        return (int) $trimmed > 0;
    }

    private function isNonNegativeNumeric(string $value): bool
    {
        $trimmed = trim($value);
        if (! is_numeric($trimmed)) {
            return false;
        }

        return (float) $trimmed >= 0;
    }
}
