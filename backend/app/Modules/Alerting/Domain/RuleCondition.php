<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

final class RuleCondition
{
    public function __construct(
        private RuleMetric $metric,
        private RuleComparator $comparator,
        private float $thresholdValue,
    ) {}

    public function metric(): RuleMetric
    {
        return $this->metric;
    }

    public function comparator(): RuleComparator
    {
        return $this->comparator;
    }

    public function thresholdValue(): float
    {
        return $this->thresholdValue;
    }

    public function isSatisfied(float $actualValue): bool
    {
        return $this->comparator->evaluate($actualValue, $this->thresholdValue);
    }
}
