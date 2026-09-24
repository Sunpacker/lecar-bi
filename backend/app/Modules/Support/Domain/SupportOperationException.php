<?php

declare(strict_types=1);

namespace App\Modules\Support\Domain;

use DomainException;

final class SupportOperationException extends DomainException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $httpStatus,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('NOT_FOUND', 'Support resource was not found', 404);
    }

    public static function conflict(string $code, string $message): self
    {
        return new self($code, $message, 409);
    }

    public static function limited(string $code, int $retryAfter): self
    {
        return new self($code, 'Support generation limit exceeded', 429, $retryAfter);
    }

    public static function invalid(string $code, string $message): self
    {
        return new self($code, $message, 422);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
