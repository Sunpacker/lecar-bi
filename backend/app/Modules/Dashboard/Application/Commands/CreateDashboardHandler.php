<?php

namespace App\Modules\Dashboard\Application\Commands;

use App\Modules\Dashboard\Application\Dtos\DashboardDetailDto;
use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use DateTimeImmutable;

final readonly class CreateDashboardHandler
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function handle(CreateDashboardCommand $command): DashboardDetailDto
    {
        $id = DashboardId::generate();
        $now = new DateTimeImmutable;

        $dashboard = new Dashboard(
            id: $id,
            workspaceId: $command->workspaceId,
            title: $command->title,
            description: $command->description,
            widgets: [],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->dashboardRepository->save($dashboard);

        return new DashboardDetailDto(
            id: $dashboard->id()->value(),
            workspaceId: $dashboard->workspaceId(),
            title: $dashboard->title(),
            description: $dashboard->description(),
            widgets: [],
            createdAt: $now->format(DATE_ATOM),
            updatedAt: $now->format(DATE_ATOM),
        );
    }
}
