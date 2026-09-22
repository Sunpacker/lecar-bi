<?php

namespace App\Modules\SalesAnalytics\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class GetSalesOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'region_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation error: '.implode(' ', $validator->errors()->all()),
            'code' => 'VALIDATION_ERROR',
        ], 422));
    }
}
