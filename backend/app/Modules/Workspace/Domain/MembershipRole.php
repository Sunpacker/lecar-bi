<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

enum MembershipRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    /**
     * @return list<WorkspaceCapability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::OWNER => [
                WorkspaceCapability::ANALYTICS_VIEW,
                WorkspaceCapability::DASHBOARDS_VIEW,
                WorkspaceCapability::DASHBOARDS_MANAGE,
                WorkspaceCapability::IMPORTS_VIEW,
                WorkspaceCapability::IMPORTS_MANAGE,
                WorkspaceCapability::ALERTS_VIEW,
                WorkspaceCapability::ALERTS_MANAGE,
                WorkspaceCapability::WORKSPACE_MEMBERS_MANAGE,
                WorkspaceCapability::WORKSPACE_SETTINGS_MANAGE,
            ],
            self::MEMBER => [
                WorkspaceCapability::ANALYTICS_VIEW,
                WorkspaceCapability::DASHBOARDS_VIEW,
                WorkspaceCapability::DASHBOARDS_MANAGE,
                WorkspaceCapability::IMPORTS_VIEW,
                WorkspaceCapability::IMPORTS_MANAGE,
                WorkspaceCapability::ALERTS_VIEW,
                WorkspaceCapability::ALERTS_MANAGE,
            ],
            self::VIEWER => [
                WorkspaceCapability::ANALYTICS_VIEW,
                WorkspaceCapability::DASHBOARDS_VIEW,
                WorkspaceCapability::IMPORTS_VIEW,
                WorkspaceCapability::ALERTS_VIEW,
            ],
        };
    }

    public function allows(WorkspaceCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
