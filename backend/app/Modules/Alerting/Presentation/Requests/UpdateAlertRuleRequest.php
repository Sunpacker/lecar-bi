<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', 'string', 'in:info,warning,critical'],
            'metric' => ['nullable', 'string', 'in:quantity_available,days_of_stock,inventory_value'],
            'comparator' => ['nullable', 'string', 'in:lt,lte,gt,gte,eq'],
            'threshold_value' => ['required', 'numeric'],
            'warehouse_id' => ['nullable', 'string', 'max:64'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'product_id' => ['nullable', 'string', 'max:64'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
