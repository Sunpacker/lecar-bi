<?php

namespace App\Modules\Dashboard\Domain;

use InvalidArgumentException;

final readonly class SavedViewId
{
    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(private string $value)
    {
        if (! preg_match(self::UUID_REGEX, $value)) {
            throw new InvalidArgumentException("Invalid UUID format for SavedViewId: '{$value}'");
        }
    }

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40); // version 4
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80); // variant RFC 4122

        $hex = bin2hex($bytes);
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );

        return new self($uuid);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
