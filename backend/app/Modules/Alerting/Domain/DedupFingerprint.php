<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

use InvalidArgumentException;

final class DedupFingerprint
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('DedupFingerprint cannot be empty.');
        }

        $this->value = $trimmed;
    }

    public static function generate(
        string $workspaceId,
        string $ruleId,
        ?string $productId = null,
        ?string $warehouseId = null
    ): self {
        $raw = sprintf(
            '%s:%s:%s:%s',
            trim($workspaceId),
            trim($ruleId),
            $productId !== null && trim($productId) !== '' ? trim($productId) : 'any',
            $warehouseId !== null && trim($warehouseId) !== '' ? trim($warehouseId) : 'any'
        );

        return new self(sha1($raw));
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
