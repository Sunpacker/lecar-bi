<?php

namespace App\Modules\DataIngestion\Domain;

use InvalidArgumentException;

final readonly class ImportBatchId
{
    private function __construct(private string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('ImportBatchId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        // Simple random v4 UUID implementation without using illuminate/support
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return new self($uuid);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
