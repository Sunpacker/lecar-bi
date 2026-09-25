<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\InvitationId;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;

final readonly class CancelInvitationHandler
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
        private InvitationRepositoryInterface $invitationRepository,
    ) {}

    public function handle(CancelInvitationCommand $command): void
    {
        $workspace = $this->workspaceRepository->findById(new WorkspaceId($command->workspaceId));
        if ($workspace === null) {
            throw new WorkspaceNotFoundException($command->workspaceId);
        }

        $invitation = $this->invitationRepository->findById(new InvitationId($command->invitationId));
        if ($invitation === null || $invitation->workspaceId()->value() !== $command->workspaceId) {
            throw new \DomainException('Invitation not found in this workspace.');
        }

        $invitation->cancel();
        $this->invitationRepository->save($invitation);
    }
}
