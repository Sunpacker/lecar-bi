<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Application\Dtos\InvitationDto;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\Invitation;
use App\Modules\Workspace\Domain\InvitationId;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Jobs\SendWorkspaceInvitationEmailJob;
use DateTimeImmutable;

final readonly class CreateInvitationHandler
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
        private InvitationRepositoryInterface $invitationRepository,
    ) {}

    public function handle(CreateInvitationCommand $command): InvitationDto
    {
        $workspace = $this->workspaceRepository->findById(new WorkspaceId($command->workspaceId));
        if ($workspace === null) {
            throw new WorkspaceNotFoundException($command->workspaceId);
        }

        $email = strtolower(trim($command->email));
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = new DateTimeImmutable('+7 days');

        // Check if pending invitation already exists for this email in this workspace
        $existing = $this->invitationRepository->findPendingByWorkspaceAndEmail(
            $workspace->id(),
            $email
        );

        if ($existing !== null) {
            $existing->renew($tokenHash, $expiresAt);
            $this->invitationRepository->save($existing);
            $invitation = $existing;
        } else {
            $invitation = new Invitation(
                id: new InvitationId('inv_'.bin2hex(random_bytes(12))),
                workspaceId: $workspace->id(),
                email: $email,
                role: $command->role,
                tokenHash: $tokenHash,
                expiresAt: $expiresAt,
            );
            $this->invitationRepository->save($invitation);
        }

        SendWorkspaceInvitationEmailJob::dispatch(
            $email,
            $workspace->name(),
            $rawToken,
            $command->role->value,
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
