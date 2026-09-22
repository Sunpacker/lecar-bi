<?php

namespace App\Modules\InventoryAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetInventoryItemsRequest extends FormRequest
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
            'warehouse_id' => ['nullable', 'string'],
            'stock_health' => ['nullable', 'string', 'in:out_of_stock,critical,optimal,overstock'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:product_name,quantity_on_hand,quantity_available,inventory_value,sales_velocity,days_of_stock'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
