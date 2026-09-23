<?php

namespace Database\Seeders;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Illuminate\Database\Seeder;

final class WorkspaceDatabaseSeeder extends Seeder
{
    public function run(
        UserRepositoryInterface $userRepository,
        WorkspaceRepositoryInterface $workspaceRepository,
    ): void {
        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $user2 = new User(new UserId('user-2'), 'dmitry@autobi.internal', 'Dmitry Smirnov');
        $user3 = new User(new UserId('user-3'), 'alexey@autobi.internal', 'Alexey Petrov');
        $user4 = new User(new UserId('user-4'), 'olga@autobi.internal', 'Olga Sidorova');

        $userRepository->save($user1);
        $userRepository->save($user2);
        $userRepository->save($user3);
        $userRepository->save($user4);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $ws1->addMember(new UserId('user-3'), MembershipRole::MEMBER);
        $ws1->addMember(new UserId('user-4'), MembershipRole::VIEWER);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);

        $workspaceRepository->save($ws1);
        $workspaceRepository->save($ws2);
    }
}
