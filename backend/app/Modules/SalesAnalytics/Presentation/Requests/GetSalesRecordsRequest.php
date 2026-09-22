<?php

namespace App\Modules\SalesAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetSalesRecordsRequest extends FormRequest
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'category_id' => ['nullable', 'string'],
            'region_id' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:order_date,order_number,product_name,total_price,quantity,gross_profit'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
