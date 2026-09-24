<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Ai;

use App\Modules\Support\Application\Contracts\ChatModel;
use RuntimeException;

final class UnconfiguredChatModel implements ChatModel
{
    public function stream(string $question, array $context): iterable
    {
        throw new RuntimeException('Support chat provider is not configured');
    }

    public function citedSourceIds(): array
    {
        return [];
    }

    public function usage(): ?array
    {
        return null;
    }

    public function provider(): string
    {
        return 'unconfigured';
    }

    public function model(): string
    {
        return 'unconfigured';
    }
}
