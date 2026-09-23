<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateAlertRuleRequest extends FormRequest
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
            'rule_type' => ['required', 'string', 'in:out_of_stock,critical_stock,overstock,reorder_point'],
            'severity' => ['required', 'string', 'in:info,warning,critical'],
            'metric' => ['required', 'string', 'in:quantity_available,days_of_stock,inventory_value'],
            'comparator' => ['required', 'string', 'in:lt,lte,gt,gte,eq'],
            'threshold_value' => ['required', 'numeric'],
            'warehouse_id' => ['nullable', 'string', 'max:64'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'product_id' => ['nullable', 'string', 'max:64'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }
}
