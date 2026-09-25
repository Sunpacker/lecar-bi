<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Application\Dtos\InvitationDto;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\InvitationId;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Jobs\SendWorkspaceInvitationEmailJob;
use DateTimeImmutable;

final readonly class ResendInvitationHandler
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
        private InvitationRepositoryInterface $invitationRepository,
    ) {}

    public function handle(ResendInvitationCommand $command): InvitationDto
    {
        $workspace = $this->workspaceRepository->findById(new WorkspaceId($command->workspaceId));
        if ($workspace === null) {
            throw new WorkspaceNotFoundException($command->workspaceId);
        }

        $invitation = $this->invitationRepository->findById(new InvitationId($command->invitationId));
        if ($invitation === null || $invitation->workspaceId()->value() !== $command->workspaceId) {
            throw new \DomainException('Invitation not found in this workspace.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = new DateTimeImmutable('+7 days');

        $invitation->renew($tokenHash, $expiresAt);
        $this->invitationRepository->save($invitation);

        SendWorkspaceInvitationEmailJob::dispatch(
            $invitation->email(),
            $workspace->name(),
            $rawToken,
            $invitation->role()->value,
        );

        return new InvitationDto(
            id: $invitation->id()->value(),
            workspaceId: $invitation->workspaceId()->value(),
            email: $invitation->email(),
            role: $invitation->role()->value,
            status: $invitation->status(),
            expiresAt: $invitation->expiresAt()->format(DATE_ATOM),
            createdAt: $invitation->createdAt()->format(DATE_ATOM),
        );
    }
}
