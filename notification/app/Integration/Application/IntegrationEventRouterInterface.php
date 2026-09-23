<?php

declare(strict_types=1);

namespace NotificationService\Integration\Application;

interface IntegrationEventRouterInterface
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function route(string $streamMessageId, array $fields): RouteResult;
}
