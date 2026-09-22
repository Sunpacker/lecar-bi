<?php

namespace Tests\Unit\Modules\SalesAnalytics\Infrastructure;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsReadModelTest extends TestCase
{
    #[Test]
    public function in_memory_read_model_satisfies_interface(): void
    {
        $readModel = new InMemorySalesAnalyticsReadModel;
        self::assertInstanceOf(SalesAnalyticsReadModelInterface::class, $readModel);

        $overview = $readModel->getSalesOverview('ws-1', new SalesFilterCriteriaDto(null, null, null, null));
        self::assertGreaterThanOrEqual(0, $overview->summary->orderCount);
        self::assertNotEmpty($overview->trend);
        self::assertNotEmpty($overview->categories);
        self::assertNotEmpty($overview->regions);

        $filters = $readModel->getFilterOptions('ws-1');
        self::assertNotEmpty($filters->minDate);
        self::assertNotEmpty($filters->maxDate);
        self::assertNotEmpty($filters->categories);
        self::assertNotEmpty($filters->regions);
    }
}
