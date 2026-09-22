<?php

namespace App\Modules\Dashboard\Domain;

final readonly class WidgetQueryConfig
{
    public function __construct(
        public DatasetType $dataset,
        public MetricType $metric,
        public ?DimensionType $dimension = null,
        public ?string $dateRange = null,
    ) {}
}
