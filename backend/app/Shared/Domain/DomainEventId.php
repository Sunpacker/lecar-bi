<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

final class DomainEventId
{
    public function __construct(private readonly string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('DomainEventId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        return new self('evt-'.bin2hex(random_bytes(16)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
