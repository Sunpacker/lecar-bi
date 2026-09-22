<?php

namespace App\Modules\SalesAnalytics\Domain;

use App\Modules\SalesAnalytics\Domain\Exceptions\InvalidDateRangeException;
use DateTimeImmutable;

final readonly class DateRange
{
    private function __construct(
        private ?string $from,
        private ?string $to,
    ) {}

    public static function create(?string $from, ?string $to): self
    {
        if ($from !== null && $to !== null) {
            $fromDate = DateTimeImmutable::createFromFormat('Y-m-d', $from);
            $toDate = DateTimeImmutable::createFromFormat('Y-m-d', $to);

            if ($fromDate !== false && $toDate !== false && $fromDate > $toDate) {
                throw InvalidDateRangeException::inverted($from, $to);
            }
        }

        return new self($from, $to);
    }

    public function from(): ?string
    {
        return $this->from;
    }

    public function to(): ?string
    {
        return $this->to;
    }
}
