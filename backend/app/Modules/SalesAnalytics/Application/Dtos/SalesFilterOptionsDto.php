<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesFilterOptionsDto
{
    /**
     * @param  list<array{id: string, name: string}>  $categories
     * @param  list<array{id: string, name: string, code: string}>  $regions
     */
    public function __construct(
        public array $categories,
        public array $regions,
        public string $minDate,
        public string $maxDate,
    ) {}
}
