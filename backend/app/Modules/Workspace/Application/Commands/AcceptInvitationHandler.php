<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application\Commands;

use App\Modules\Workspace\Application\Contracts\AuthTokenServiceInterface;
use App\Modules\Workspace\Application\Contracts\WorkspaceTransactionManagerInterface;
use App\Modules\Workspace\Application\Dtos\UserDto;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\Repositories\InvitationRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use DateTimeImmutable;

final readonly class AcceptInvitationHandler
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepository,
        private WorkspaceRepositoryInterface $workspaceRepository,
        private UserRepositoryInterface $userRepository,
        private AuthTokenServiceInterface $tokenService,
        private WorkspaceTransactionManagerInterface $transactionManager,
    ) {}

    /**
     * @return array{user: UserDto, workspace_id: string, token: string}
     */
    public function handle(AcceptInvitationCommand $command): array
    {
        $tokenHash = hash('sha256', $command->rawToken);
        $invitation = $this->invitationRepository->findByTokenHash($tokenHash);

        if ($invitation === null) {
            throw new \DomainException('Приглашение не найдено.');
        }

        if (! $invitation->isPending()) {
            throw new \DomainException('Приглашение уже было использовано или отменено.');
        }

        $now = new DateTimeImmutable('now');
        if ($invitation->isExpired($now)) {
            throw new \DomainException('Срок действия приглашения истек.');
        }

        return $this->transactionManager->transaction(function () use ($invitation, $command, $now): array {
            $workspace = $this->workspaceRepository->findById($invitation->workspaceId());
            if ($workspace === null) {
                throw new WorkspaceNotFoundException($invitation->workspaceId()->value());
            }

            // Check if user exists
            $user = $this->userRepository->findByEmail($invitation->email());

            if ($user === null) {
                if (empty($command->name) || empty($command->password)) {
                    throw new \InvalidArgumentException('Для создания нового аккаунта требуются имя и пароль.');
                }

                $newUserId = new UserId('user-'.bin2hex(random_bytes(6)));
                $passwordHash = password_hash($command->password, PASSWORD_DEFAULT);

                $user = new User(
                    id: $newUserId,
                    email: $invitation->email(),
                    name: trim($command->name),
                    passwordHash: $passwordHash,
                );
                $this->userRepository->save($user);
            }

            // Add user to workspace if not already a member
            if (! $workspace->hasMember($user->id())) {
                $workspace->addMember($user->id(), $invitation->role());
                $this->workspaceRepository->save($workspace);
            }

            // Mark invitation accepted
            $invitation->accept($now);
            $this->invitationRepository->save($invitation);

            // Generate authentication token
            $token = $this->tokenService->createToken($user->id()->value());

            return [
                'user' => new UserDto(
                    id: $user->id()->value(),
                    email: $user->email(),
                    name: $user->name(),
                ),
                'workspace_id' => $workspace->id()->value(),
                'token' => $token,
            ];
        });
    }
}
