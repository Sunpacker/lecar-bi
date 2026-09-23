<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetSupplierOverviewRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'string'],
            'warehouse_id' => ['nullable', 'string'],
        ];
    }
}
