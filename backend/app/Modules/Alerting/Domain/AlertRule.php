<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class AlertRule
{
    private AlertRuleId $id;

    private string $workspaceId;

    private string $name;

    private ?string $description;

    private RuleType $ruleType;

    private AlertSeverity $severity;

    private RuleCondition $condition;

    private RuleScope $scope;

    private bool $isEnabled;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        AlertRuleId $id,
        string $workspaceId,
        string $name,
        ?string $description,
        RuleType $ruleType,
        AlertSeverity $severity,
        RuleCondition $condition,
        RuleScope $scope,
        bool $isEnabled,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Alert rule name cannot be empty.');
        }

        $trimmedWs = trim($workspaceId);
        if ($trimmedWs === '') {
            throw new InvalidArgumentException('Alert rule workspaceId cannot be empty.');
        }

        $this->id = $id;
        $this->workspaceId = $trimmedWs;
        $this->name = $trimmedName;
        $this->description = $description !== null ? trim($description) : null;
        $this->ruleType = $ruleType;
        $this->severity = $severity;
        $this->condition = $condition;
        $this->scope = $scope;
        $this->isEnabled = $isEnabled;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): AlertRuleId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function ruleType(): RuleType
    {
        return $this->ruleType;
    }

    public function severity(): AlertSeverity
    {
        return $this->severity;
    }

    public function condition(): RuleCondition
    {
        return $this->condition;
    }

    public function scope(): RuleScope
    {
        return $this->scope;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function enable(): void
    {
        $this->isEnabled = true;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function disable(): void
    {
        $this->isEnabled = false;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function toggle(): bool
    {
        $this->isEnabled = ! $this->isEnabled;
        $this->updatedAt = new DateTimeImmutable;

        return $this->isEnabled;
    }

    public function update(
        string $name,
        ?string $description,
        AlertSeverity $severity,
        RuleCondition $condition,
        RuleScope $scope,
        ?bool $isEnabled = null
    ): void {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidArgumentException('Alert rule name cannot be empty.');
        }

        $this->name = $trimmedName;
        $this->description = $description !== null ? trim($description) : null;
        $this->severity = $severity;
        $this->condition = $condition;
        $this->scope = $scope;
        if ($isEnabled !== null) {
            $this->isEnabled = $isEnabled;
        }
        $this->updatedAt = new DateTimeImmutable;
    }
}
