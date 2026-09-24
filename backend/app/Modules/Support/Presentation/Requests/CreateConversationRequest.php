<?php

declare(strict_types=1);

namespace App\Modules\Support\Presentation\Requests;

final class CreateConversationRequest extends SupportFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['title' => ['sometimes', 'nullable', 'string', 'min:1', 'max:120']];
    }
}
