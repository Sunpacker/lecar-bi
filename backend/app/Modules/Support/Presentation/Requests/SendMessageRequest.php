<?php

declare(strict_types=1);

namespace App\Modules\Support\Presentation\Requests;

final class SendMessageRequest extends SupportFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_message_id' => ['required', 'uuid'],
            'content' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }
}
