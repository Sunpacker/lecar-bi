<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InventoryAnalytics\Application;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzItemsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzItemsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzSummaryHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzSummaryQuery;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbcXyzApplicationTest extends TestCase
{
    private InMemoryInventoryAnalyticsReadModel $readModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->readModel = new InMemoryInventoryAnalyticsReadModel;
    }

    #[Test]
    public function it_handles_get_abc_xyz_summary_query(): void
    {
        $handler = new GetAbcXyzSummaryHandler($this->readModel);
        $query = new GetAbcXyzSummaryQuery('ws-1', new AbcXyzSummaryCriteriaDto(periodDays: 90));

        $result = $handler->handle($query);

        self::assertSame(5, $result->totalProducts);
        self::assertSame(300000.0, $result->totalRevenue);
        self::assertCount(9, $result->matrix);
        self::assertCount(3, $result->abcDistribution);
        self::assertCount(3, $result->xyzDistribution);
        self::assertSame(90, $result->periodDays);
    }

    #[Test]
    public function it_handles_get_abc_xyz_items_query_with_filtering_and_pagination(): void
    {
        $handler = new GetAbcXyzItemsHandler($this->readModel);

        // Fetch all items (page 1, per_page 10)
        $queryAll = new GetAbcXyzItemsQuery('ws-1', new AbcXyzItemsCriteriaDto(page: 1, perPage: 10));
        $resultAll = $handler->handle($queryAll);

        self::assertSame(5, $resultAll->total);
        self::assertCount(5, $resultAll->items);
        self::assertSame(1, $resultAll->totalPages);

        // Filter by category
        $queryCat = new GetAbcXyzItemsQuery('ws-1', new AbcXyzItemsCriteriaDto(categoryId: 'cat-tires-wheels'));
        $resultCat = $handler->handle($queryCat);
        self::assertSame(2, $resultCat->total);

        // Filter by ABC class
        $queryAbc = new GetAbcXyzItemsQuery('ws-1', new AbcXyzItemsCriteriaDto(abcClass: 'A'));
        $resultAbc = $handler->handle($queryAbc);
        self::assertNotEmpty($resultAbc->items);
        foreach ($resultAbc->items as $item) {
            self::assertSame('A', $item->abcClass);
        }

        // Search by keyword
        $querySearch = new GetAbcXyzItemsQuery('ws-1', new AbcXyzItemsCriteriaDto(search: 'Continental'));
        $resultSearch = $handler->handle($querySearch);
        self::assertSame(1, $resultSearch->total);
        self::assertSame('prod-conti-wint-16', $resultSearch->items[0]->productId);
    }
}
