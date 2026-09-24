<?php

declare(strict_types=1);

namespace App\Modules\Support\Application\Contracts;

interface GenerationDispatcherInterface
{
    public function dispatch(string $generationId): void;
}
