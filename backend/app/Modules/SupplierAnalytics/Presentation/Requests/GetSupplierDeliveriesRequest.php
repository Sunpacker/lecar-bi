<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetSupplierDeliveriesRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'string'],
            'warehouse_id' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:on_time,delayed,partial'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:order_date,expected_delivery_date,actual_delivery_date,lead_time_days,delay_days,total_purchase_cost'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
