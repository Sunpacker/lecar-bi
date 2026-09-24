<?php

namespace Tests\Unit;

use App\Shared\Infrastructure\Modules\BoundedContextRegistry;
use Tests\TestCase;

final class BoundedContextRegistryTest extends TestCase
{
    public function test_all_initial_bounded_contexts_are_registered(): void
    {
        $registry = $this->app->make(BoundedContextRegistry::class);

        self::assertSame([
            'Workspace',
            'DataIngestion',
            'SalesAnalytics',
            'InventoryAnalytics',
            'SupplierAnalytics',
            'Dashboard',
            'Alerting',
            'Support',
            'KnowledgeBase',
        ], $registry->names());
        self::assertTrue($registry->has('SalesAnalytics'));
        self::assertFalse($registry->has('Unknown'));
    }
}
