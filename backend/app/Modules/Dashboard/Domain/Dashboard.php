<?php

namespace App\Modules\Dashboard\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class Dashboard
{
    /** @var array<string, Widget> */
    private array $widgets = [];

    /**
     * @param  list<Widget>  $widgets
     */
    public function __construct(
        private readonly DashboardId $id,
        private readonly string $workspaceId,
        private string $title,
        private ?string $description = null,
        array $widgets = [],
        private readonly ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
    ) {
        $trimmedWorkspaceId = trim($workspaceId);
        if ($trimmedWorkspaceId === '') {
            throw new InvalidArgumentException('Workspace ID cannot be empty.');
        }
        $this->setTitle($title);
        $this->setDescription($description);

        foreach ($widgets as $widget) {
            $this->widgets[$widget->id()->value()] = $widget;
        }
    }

    public function id(): DashboardId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function rename(string $title, ?string $description = null): void
    {
        $this->setTitle($title);
        $this->setDescription($description);
        $this->updatedAt = new DateTimeImmutable;
    }

    public function addWidget(Widget $widget): void
    {
        $this->widgets[$widget->id()->value()] = $widget;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function removeWidget(WidgetId $widgetId): void
    {
        unset($this->widgets[$widgetId->value()]);
        $this->updatedAt = new DateTimeImmutable;
    }

    /**
     * @param  list<Widget>  $widgets
     */
    public function replaceWidgets(array $widgets): void
    {
        $this->widgets = [];
        foreach ($widgets as $widget) {
            $this->widgets[$widget->id()->value()] = $widget;
        }
        $this->updatedAt = new DateTimeImmutable;
    }

    /**
     * @return list<Widget>
     */
    public function widgets(): array
    {
        return array_values($this->widgets);
    }

    public function widgetCount(): int
    {
        return count($this->widgets);
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function setTitle(string $title): void
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Dashboard title cannot be empty.');
        }
        $this->title = $trimmed;
    }

    private function setDescription(?string $description): void
    {
        $this->description = $description !== null ? trim($description) : null;
        if ($this->description === '') {
            $this->description = null;
        }
    }
}
