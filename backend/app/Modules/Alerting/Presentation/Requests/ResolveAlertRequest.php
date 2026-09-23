<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveAlertRequest extends FormRequest
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
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
