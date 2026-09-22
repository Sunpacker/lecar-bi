<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Presentation\Requests;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use Illuminate\Foundation\Http\FormRequest;

final class GetAbcXyzItemsRequest extends FormRequest
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
            'abc_class' => ['nullable', 'string', 'in:A,B,C'],
            'xyz_class' => ['nullable', 'string', 'in:X,Y,Z'],
            'group' => ['nullable', 'string', 'in:AX,AY,AZ,BX,BY,BZ,CX,CY,CZ'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:product_name,total_revenue,revenue_share,cumulative_revenue_share,total_units_sold,coefficient_of_variation,current_stock,inventory_value'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function toCriteria(): AbcXyzItemsCriteriaDto
    {
        return new AbcXyzItemsCriteriaDto(
            periodDays: $this->filled('period_days') ? (int) $this->query('period_days') : 90,
            warehouseId: $this->filled('warehouse_id') ? (string) $this->query('warehouse_id') : null,
            categoryId: $this->filled('category_id') ? (string) $this->query('category_id') : null,
            supplierId: $this->filled('supplier_id') ? (string) $this->query('supplier_id') : null,
            abcClass: $this->filled('abc_class') ? (string) $this->query('abc_class') : null,
            xyzClass: $this->filled('xyz_class') ? (string) $this->query('xyz_class') : null,
            group: $this->filled('group') ? (string) $this->query('group') : null,
            search: $this->filled('search') ? (string) $this->query('search') : null,
            page: $this->filled('page') ? (int) $this->query('page') : 1,
            perPage: $this->filled('per_page') ? (int) $this->query('per_page') : 20,
            sortBy: $this->filled('sort_by') ? (string) $this->query('sort_by') : 'total_revenue',
            sortDirection: $this->filled('sort_direction') ? (string) $this->query('sort_direction') : 'desc',
        );
    }
}
