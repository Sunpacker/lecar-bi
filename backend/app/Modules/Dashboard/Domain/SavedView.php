<?php

namespace App\Modules\Dashboard\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class SavedView
{
    public function __construct(
        private readonly SavedViewId $id,
        private readonly DashboardId $dashboardId,
        private string $name,
        private DashboardFilters $filters,
        private bool $isDefault = false,
        private readonly ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->setName($name);
    }

    public function id(): SavedViewId
    {
        return $this->id;
    }

    public function dashboardId(): DashboardId
    {
        return $this->dashboardId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Saved view name cannot be empty.');
        }
        $this->name = $trimmed;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function rename(string $name): void
    {
        $this->setName($name);
    }

    public function filters(): DashboardFilters
    {
        return $this->filters;
    }

    public function updateFilters(DashboardFilters $filters): void
    {
        $this->filters = $filters;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function markAsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
