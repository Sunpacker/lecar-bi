<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetAlertsRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:all,active,open,acknowledged,resolved'],
            'severity' => ['nullable', 'string', 'in:info,warning,critical'],
            'warehouse_id' => ['nullable', 'string', 'max:64'],
            'rule_id' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
