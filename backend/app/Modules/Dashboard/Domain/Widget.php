<?php

namespace App\Modules\Dashboard\Domain;

use InvalidArgumentException;

final class Widget
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        private readonly WidgetId $id,
        private string $title,
        private WidgetType $type,
        private WidgetQueryConfig $queryConfig,
        private WidgetGridPosition $position,
        private array $options = [],
    ) {
        $this->setTitle($title);
    }

    public function id(): WidgetId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Widget title cannot be empty.');
        }
        $this->title = $trimmed;
    }

    public function type(): WidgetType
    {
        return $this->type;
    }

    public function setType(WidgetType $type): void
    {
        $this->type = $type;
    }

    public function queryConfig(): WidgetQueryConfig
    {
        return $this->queryConfig;
    }

    public function setQueryConfig(WidgetQueryConfig $queryConfig): void
    {
        $this->queryConfig = $queryConfig;
    }

    public function position(): WidgetGridPosition
    {
        return $this->position;
    }

    public function setPosition(WidgetGridPosition $position): void
    {
        $this->position = $position;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
