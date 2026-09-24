<?php

declare(strict_types=1);

namespace App\Modules\Support\Application\Contracts;

interface ChatModel
{
    /**
     * @param  list<array<string, mixed>>  $context
     * @return iterable<string>
     */
    public function stream(string $question, array $context): iterable;

    /** @return list<string> */
    public function citedSourceIds(): array;

    /** @return array{input_tokens: int, output_tokens: int, reasoning_tokens: int}|null */
    public function usage(): ?array;

    public function provider(): string;

    public function model(): string;
}
