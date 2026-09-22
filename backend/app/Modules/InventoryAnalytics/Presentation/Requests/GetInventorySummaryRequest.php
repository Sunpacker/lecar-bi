<?php

namespace App\Modules\InventoryAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetInventorySummaryRequest extends FormRequest
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
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
