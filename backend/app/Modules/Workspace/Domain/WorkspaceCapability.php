<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

enum WorkspaceCapability: string
{
    case ANALYTICS_VIEW = 'analytics.view';
    case DASHBOARDS_VIEW = 'dashboards.view';
    case DASHBOARDS_MANAGE = 'dashboards.manage';
    case IMPORTS_VIEW = 'imports.view';
    case IMPORTS_MANAGE = 'imports.manage';
    case ALERTS_VIEW = 'alerts.view';
    case ALERTS_MANAGE = 'alerts.manage';
    case WORKSPACE_MEMBERS_MANAGE = 'workspace.members.manage';
    case WORKSPACE_SETTINGS_MANAGE = 'workspace.settings.manage';
}
