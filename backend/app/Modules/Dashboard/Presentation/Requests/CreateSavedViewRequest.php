<?php

namespace App\Modules\Dashboard\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CreateSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'filters' => ['required', 'array'],
            'filters.date_range' => ['nullable', 'string', 'in:30d,90d,180d,365d,all,custom'],
            'filters.date_from' => ['nullable', 'date_format:Y-m-d'],
            'filters.date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filters.date_from'],
            'filters.category_id' => ['nullable', 'string'],
            'filters.region_id' => ['nullable', 'string'],
            'filters.warehouse_id' => ['nullable', 'string'],
            'filters.stock_health' => ['nullable', 'string', 'in:in_stock,low_stock,out_of_stock,overstock'],
            'is_default' => ['nullable', 'boolean'],
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
