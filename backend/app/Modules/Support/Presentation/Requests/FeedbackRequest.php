<?php

declare(strict_types=1);

namespace App\Modules\Support\Presentation\Requests;

final class FeedbackRequest extends SupportFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['rating' => ['required', 'string', 'in:helpful,not_helpful']];
    }
}
