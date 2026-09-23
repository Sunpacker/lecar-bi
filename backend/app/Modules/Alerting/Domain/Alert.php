<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

use App\Modules\Alerting\Domain\Events\AlertTriggered;
use App\Modules\Alerting\Domain\Exceptions\InvalidAlertStateTransitionException;
use App\Shared\Domain\DomainEventId;
use App\Shared\Domain\HasDomainEvents;
use DateTimeImmutable;
use InvalidArgumentException;

final class Alert
{
    use HasDomainEvents;

    private AlertId $id;

    private string $workspaceId;

    private ?AlertRuleId $ruleId;

    private string $ruleName;

    private AlertSeverity $severity;

    private AlertStatus $status;

    private DedupFingerprint $dedupFingerprint;

    private AlertContext $context;

    private DateTimeImmutable $triggeredAt;

    private ?DateTimeImmutable $acknowledgedAt = null;

    private ?string $acknowledgedBy = null;

    private ?DateTimeImmutable $resolvedAt = null;

    private ?string $resolvedBy = null;

    private ?string $resolutionNote = null;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        AlertId $id,
        string $workspaceId,
        ?AlertRuleId $ruleId,
        string $ruleName,
        AlertSeverity $severity,
        AlertStatus $status,
        DedupFingerprint $dedupFingerprint,
        AlertContext $context,
        DateTimeImmutable $triggeredAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $acknowledgedAt = null,
        ?string $acknowledgedBy = null,
        ?DateTimeImmutable $resolvedAt = null,
        ?string $resolvedBy = null,
        ?string $resolutionNote = null,
    ) {
        $trimmedRuleName = trim($ruleName);
        if ($trimmedRuleName === '') {
            throw new InvalidArgumentException('Alert ruleName cannot be empty.');
        }

        $trimmedWs = trim($workspaceId);
        if ($trimmedWs === '') {
            throw new InvalidArgumentException('Alert workspaceId cannot be empty.');
        }

        $this->id = $id;
        $this->workspaceId = $trimmedWs;
        $this->ruleId = $ruleId;
        $this->ruleName = $trimmedRuleName;
        $this->severity = $severity;
        $this->status = $status;
        $this->dedupFingerprint = $dedupFingerprint;
        $this->context = $context;
        $this->triggeredAt = $triggeredAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->acknowledgedAt = $acknowledgedAt;
        $this->acknowledgedBy = $acknowledgedBy !== null ? trim($acknowledgedBy) : null;
        $this->resolvedAt = $resolvedAt;
        $this->resolvedBy = $resolvedBy !== null ? trim($resolvedBy) : null;
        $this->resolutionNote = $resolutionNote !== null ? trim($resolutionNote) : null;
    }

    /**
     * Factory method: creates a new Alert and records the AlertTriggered domain event.
     * The event_id and timestamp are passed explicitly — no framework clock or helpers inside Domain.
     */
    public static function trigger(
        AlertId $id,
        DomainEventId $eventId,
        string $workspaceId,
        ?AlertRuleId $ruleId,
        string $ruleName,
        AlertSeverity $severity,
        DedupFingerprint $dedupFingerprint,
        AlertContext $context,
        RuleMetric $metric,
        RuleComparator $comparator,
        DateTimeImmutable $now,
    ): self {
        $alert = new self(
            id: $id,
            workspaceId: $workspaceId,
            ruleId: $ruleId,
            ruleName: $ruleName,
            severity: $severity,
            status: AlertStatus::OPEN,
            dedupFingerprint: $dedupFingerprint,
            context: $context,
            triggeredAt: $now,
            createdAt: $now,
            updatedAt: $now,
        );

        $alert->recordDomainEvent(new AlertTriggered(
            eventId: $eventId,
            alertId: $id,
            workspaceId: $workspaceId,
            ruleId: $ruleId,
            ruleName: $ruleName,
            severity: $severity,
            metric: $metric,
            comparator: $comparator,
            currentValue: $context->currentValue() ?? 0.0,
            thresholdValue: $context->thresholdValue() ?? 0.0,
            context: $context,
            occurredAt: $now,
        ));

        return $alert;
    }

    public function id(): AlertId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function ruleId(): ?AlertRuleId
    {
        return $this->ruleId;
    }

    public function ruleName(): string
    {
        return $this->ruleName;
    }

    public function severity(): AlertSeverity
    {
        return $this->severity;
    }

    public function status(): AlertStatus
    {
        return $this->status;
    }

    public function dedupFingerprint(): DedupFingerprint
    {
        return $this->dedupFingerprint;
    }

    public function context(): AlertContext
    {
        return $this->context;
    }

    public function triggeredAt(): DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function acknowledgedAt(): ?DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function acknowledgedBy(): ?string
    {
        return $this->acknowledgedBy;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function resolvedBy(): ?string
    {
        return $this->resolvedBy;
    }

    public function resolutionNote(): ?string
    {
        return $this->resolutionNote;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isOpen(): bool
    {
        return $this->status === AlertStatus::OPEN;
    }

    public function isAcknowledged(): bool
    {
        return $this->status === AlertStatus::ACKNOWLEDGED;
    }

    public function isResolved(): bool
    {
        return $this->status === AlertStatus::RESOLVED;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function acknowledge(string $userId, ?DateTimeImmutable $at = null): void
    {
        if ($this->status === AlertStatus::RESOLVED) {
            throw new InvalidAlertStateTransitionException('Cannot acknowledge an already resolved alert.');
        }

        $this->status = AlertStatus::ACKNOWLEDGED;
        $this->acknowledgedAt = $at ?? new DateTimeImmutable;
        $this->acknowledgedBy = trim($userId);
        $this->updatedAt = new DateTimeImmutable;
    }

    public function resolve(string $userId, ?string $note = null, ?DateTimeImmutable $at = null): void
    {
        if ($this->status === AlertStatus::RESOLVED) {
            return;
        }

        $this->status = AlertStatus::RESOLVED;
        $this->resolvedAt = $at ?? new DateTimeImmutable;
        $this->resolvedBy = trim($userId);
        $this->resolutionNote = $note !== null ? trim($note) : null;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function retrigger(float $currentValue, ?DateTimeImmutable $at = null): void
    {
        // Retrigger does NOT record a new domain event — it only updates state on an existing active alert.
        $this->context = $this->context->withCurrentValue($currentValue);
        $this->triggeredAt = $at ?? new DateTimeImmutable;
        $this->updatedAt = new DateTimeImmutable;
    }
}
