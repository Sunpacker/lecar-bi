<?php

declare(strict_types=1);

namespace NotificationService\Shared\Infrastructure\Health;

interface DependencyHealthCheckerInterface
{
    /**
     * @return array{database: 'ok'|'error', redis: 'ok'|'error'}
     */
    public function check(): array;
}
