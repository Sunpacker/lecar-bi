<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetSupplierPerformanceRequest extends FormRequest
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
            'warehouse_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:supplier_name,total_deliveries,total_spend,on_time_rate,fulfillment_rate,defect_rate,avg_lead_time_days,reliability_score'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
