<?php

namespace Tests\Unit\Modules\SalesAnalytics\Domain;

use App\Modules\SalesAnalytics\Domain\DateRange;
use App\Modules\SalesAnalytics\Domain\Exceptions\InvalidDateRangeException;
use App\Modules\SalesAnalytics\Domain\SalesMetrics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsDomainTest extends TestCase
{
    #[Test]
    public function it_creates_valid_date_range(): void
    {
        $range = DateRange::create('2025-01-01', '2025-01-31');
        self::assertSame('2025-01-01', $range->from());
        self::assertSame('2025-01-31', $range->to());
    }

    #[Test]
    public function it_allows_null_boundaries(): void
    {
        $range = DateRange::create(null, null);
        self::assertNull($range->from());
        self::assertNull($range->to());
    }

    #[Test]
    public function it_throws_when_from_date_is_after_to_date(): void
    {
        $this->expectException(InvalidDateRangeException::class);
        DateRange::create('2025-02-01', '2025-01-01');
    }

    #[Test]
    public function it_calculates_aov_correctly(): void
    {
        self::assertSame(150.0, SalesMetrics::calculateAov(300.0, 2));
        self::assertSame(0.0, SalesMetrics::calculateAov(0.0, 0));
        self::assertSame(0.0, SalesMetrics::calculateAov(500.0, 0));
    }

    #[Test]
    public function it_calculates_margin_rate_and_shares(): void
    {
        self::assertSame(0.25, SalesMetrics::calculateMarginRate(1000.0, 250.0));
        self::assertSame(0.0, SalesMetrics::calculateMarginRate(0.0, 0.0));
        self::assertSame(0.4, SalesMetrics::calculateShare(400.0, 1000.0));
        self::assertSame(0.0, SalesMetrics::calculateShare(100.0, 0.0));
    }
}
