<?php

namespace App\Shared\Infrastructure\Modules;

final readonly class BoundedContextRegistry
{
    /** @param list<string> $contextNames */
    public function __construct(private array $contextNames) {}

    /** @return list<string> */
    public function names(): array
    {
        return $this->contextNames;
    }

    public function has(string $contextName): bool
    {
        return in_array($contextName, $this->contextNames, true);
    }
}
