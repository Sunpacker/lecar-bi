<?php

declare(strict_types=1);

namespace App\Modules\Support\Presentation\Requests;

final class RetryGenerationRequest extends SupportFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['retry_request_id' => ['required', 'uuid']];
    }
}
