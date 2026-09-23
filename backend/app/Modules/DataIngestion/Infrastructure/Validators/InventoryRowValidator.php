<?php

namespace App\Modules\DataIngestion\Infrastructure\Validators;

use App\Modules\DataIngestion\Domain\RowError;

/**
 * Validates an Inventory dataset row.
 *
 * Fields: snapshot_date, warehouse_code, sku,
 *         quantity_on_hand (>=0), quantity_reserved (>=0),
 *         safety_stock (>=0), reorder_point (>=0), unit_cost (>=0)
 */
final class InventoryRowValidator implements RowValidatorInterface
{
    /**
     * @param  array<string, string>  $row
     * @return list<RowError>
     */
    public function validate(array $row, int $rowNumber): array
    {
        $errors = [];

        // Required non-empty string fields
        foreach (['warehouse_code', 'sku'] as $field) {
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
        $snapshotDate = $row['snapshot_date'] ?? '';
        if (! $this->isValidDate($snapshotDate)) {
            $errors[] = new RowError(
                rowNumber: $rowNumber,
                field: 'snapshot_date',
                value: $snapshotDate,
                message: "Field 'snapshot_date' must be a valid date in YYYY-MM-DD format."
            );
        }

        // Non-negative integer fields
        foreach (['quantity_on_hand', 'quantity_reserved', 'safety_stock', 'reorder_point'] as $field) {
            $value = $row[$field] ?? '';
            if (! $this->isNonNegativeInteger($value)) {
                $errors[] = new RowError(
                    rowNumber: $rowNumber,
                    field: $field,
                    value: $value,
                    message: "Field '{$field}' must be an integer >= 0."
                );
            }
        }

        // unit_cost >= 0 (numeric)
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

    private function isNonNegativeInteger(string $value): bool
    {
        $trimmed = trim($value);
        if (! is_numeric($trimmed)) {
            return false;
        }

        return (int) $trimmed >= 0 && (string) (int) $trimmed === (string) (float) $trimmed;
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
