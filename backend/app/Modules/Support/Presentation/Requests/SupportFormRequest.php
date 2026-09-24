<?php

declare(strict_types=1);

namespace App\Modules\Support\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

abstract class SupportFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(new JsonResponse([
            'message' => 'Validation error',
            'code' => 'VALIDATION_ERROR',
            'details' => $validator->errors()->toArray(),
        ], 422));
    }
}
