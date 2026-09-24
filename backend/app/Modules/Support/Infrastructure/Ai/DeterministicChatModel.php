<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Ai;

use App\Modules\Support\Application\Contracts\ChatModel;

final class DeterministicChatModel implements ChatModel
{
    /** @var list<string> */
    private array $citations = [];

    /** @return iterable<string> */
    public function stream(string $question, array $context): iterable
    {
        $first = $context[0];
        $this->citations = [(string) $first['source_key']];
        $content = trim((string) $first['content']);
        $summary = mb_substr($content, 0, 700);

        yield 'По документации AutoBI: ';
        yield $summary;
    }

    public function citedSourceIds(): array
    {
        return $this->citations;
    }

    public function usage(): ?array
    {
        return null;
    }

    public function provider(): string
    {
        return 'deterministic';
    }

    public function model(): string
    {
        return 'support-fake-v1';
    }
}
