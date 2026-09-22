<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Presentation\Requests;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use Illuminate\Foundation\Http\FormRequest;

final class GetAbcXyzSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'period_days' => ['nullable', 'integer', 'in:30,90,180,365'],
            'warehouse_id' => ['nullable', 'string'],
            'category_id' => ['nullable', 'string'],
            'supplier_id' => ['nullable', 'string'],
        ];
    }

    public function toCriteria(): AbcXyzSummaryCriteriaDto
    {
        return new AbcXyzSummaryCriteriaDto(
            periodDays: $this->filled('period_days') ? (int) $this->query('period_days') : 90,
            warehouseId: $this->filled('warehouse_id') ? (string) $this->query('warehouse_id') : null,
            categoryId: $this->filled('category_id') ? (string) $this->query('category_id') : null,
            supplierId: $this->filled('supplier_id') ? (string) $this->query('supplier_id') : null,
        );
    }
}
