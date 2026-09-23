<?php

namespace App\Modules\DataIngestion\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class UploadImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:51200'],
            'dataset_type' => ['required', 'string', 'in:sales,inventory'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            new JsonResponse([
                'message' => 'Validation error',
                'code' => 'VALIDATION_ERROR',
                'details' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
