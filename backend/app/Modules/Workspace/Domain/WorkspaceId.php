<?php

namespace App\Modules\Workspace\Domain;

use InvalidArgumentException;

final readonly class WorkspaceId
{
    public function __construct(private string $value)
    {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException('WorkspaceId cannot be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
