<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Queries;

use App\Modules\Workspace\Application\Dtos\InvitationDto;
use App\Modules\Workspace\Domain\Invitation;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\WorkspaceId;

final readonly class ListInvitationsHandler
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepository,
    ) {}

    /**
     * @return list<InvitationDto>
     */
    public function handle(ListInvitationsQuery $query): array
    {
        $invitations = $this->invitationRepository->listPendingByWorkspace(
            new WorkspaceId($query->workspaceId)
        );

        return array_map(
            fn (Invitation $invitation) => new InvitationDto(
                id: $invitation->id()->value(),
                workspaceId: $invitation->workspaceId()->value(),
                email: $invitation->email(),
                role: $invitation->role()->value,
                status: $invitation->status(),
                expiresAt: $invitation->expiresAt()->format(DATE_ATOM),
                createdAt: $invitation->createdAt()->format(DATE_ATOM),
            ),
            $invitations
        );
    }
}
