<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Queries;

use App\Modules\Workspace\Application\Dtos\InvitationPublicDto;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use DateTimeImmutable;

final readonly class GetInvitationDetailsHandler
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepository,
        private WorkspaceRepositoryInterface $workspaceRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(GetInvitationDetailsQuery $query): InvitationPublicDto
    {
        $tokenHash = hash('sha256', $query->rawToken);
        $invitation = $this->invitationRepository->findByTokenHash($tokenHash);

        if ($invitation === null || ! $invitation->isPending()) {
            throw new \DomainException('Приглашение не найдено или более недействительно.');
        }

        $workspace = $this->workspaceRepository->findById($invitation->workspaceId());
        $workspaceName = $workspace !== null ? $workspace->name() : 'Рабочее пространство';

        $existingUser = $this->userRepository->findByEmail($invitation->email());
        $isExpired = $invitation->isExpired(new DateTimeImmutable('now'));

        return new InvitationPublicDto(
            email: $invitation->email(),
            workspaceName: $workspaceName,
            role: $invitation->role()->value,
            isExpired: $isExpired,
            isExistingUser: $existingUser !== null,
        );
    }
}
