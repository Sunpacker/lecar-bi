<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

final readonly class InvitationId
{
    public function __construct(
        private string $value
    ) {
        if (trim($this->value) === '') {
            throw new \InvalidArgumentException('Invitation ID cannot be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
