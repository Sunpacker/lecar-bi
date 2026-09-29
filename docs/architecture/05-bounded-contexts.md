# 05. Bounded Contexts

## General Principle

The backend is divided into bounded contexts, each responsible for a distinct area of the domain model.

A bounded context must have:

- its own terminology;
- a clear responsibility;
- an internal model;
- public ways to interact with other contexts;
- minimal knowledge of neighboring modules' internals.

## Initial Bounded Contexts

### Workspace

Responsible for the user's organizational scope, resource ownership, and access control (RBAC).

Core concepts:

- **Workspaces:** isolated tenants (`WorkspaceId`, `slug`, `name`).
- **Role model:** a fixed set of roles (`owner`, `member`, `viewer`) stored in the `workspace_members` table.
- **Workspace aggregate invariant:** a workspace must retain at least one active owner (`owner`). An attempt to demote the last owner is blocked by domain logic and a transactional aggregate lock (`LastWorkspaceOwnerException` → HTTP `409 LAST_WORKSPACE_OWNER`).
- **Capabilities system:** a pure domain mapping from a role to a closed set of 9 atomic permissions:
  - `owner`: all 9 permissions, including `workspace.members.manage` and `workspace.settings.manage`;
  - `member`: operational permissions (`analytics.view`, `dashboards.view`, `dashboards.manage`, `imports.view`, `imports.manage`, `alerts.view`, `alerts.manage`), without member or settings management;
  - `viewer`: read-only permissions (`analytics.view`, `dashboards.view`, `imports.view`, `alerts.view`).
- **Invitations:** the `Invitation` aggregate with statuses (`pending`, `accepted`, `cancelled`), a hashed link token valid for 7 days, delivery via the Laravel Mail `mail` queue, and atomic acceptance (`AcceptInvitationCommand`).
- **CQRS operations:**
  - Commands: `ChangeWorkspaceMemberRoleCommand`, `RenameWorkspaceCommand`, `CreateInvitationCommand`, `ResendInvitationCommand`, `CancelInvitationCommand`, `AcceptInvitationCommand`, `UpdateProfileCommand`, `ChangePasswordCommand`.
  - Queries: `GetWorkspaceMembersQuery`, `GetCurrentWorkspaceQuery`, `GetAccessibleWorkspacesQuery`, `ListInvitationsQuery`, `GetInvitationDetailsQuery`.
- **Centralized Guard:** `WorkspaceAccessGuard` checks membership and the required capability for an incoming request.

### Data Ingestion

Responsible for bringing data into the system.

Main tasks:

- dataset import;
- validation;
- staging;
- status tracking;
- normalization;
- initiating analytical projection builds.

### Sales Analytics

Responsible for:

- sales metrics;
- revenue;
- orders;
- average order value;
- trends;
- category analytics;
- regional analytics;
- metric breakdowns.

### Inventory Analytics

Responsible for:

- stock levels;
- days of stock;
- shortages;
- overstock;
- inventory turnover;
- ABC/XYZ;
- analytical breakdowns by warehouse and product.

### Supplier Analytics

Responsible for:

- supplier performance;
- delivery lead times;
- delay rates;
- delivery completeness;
- delivery quality;
- comparative analytics.

### Dashboard

Responsible for user dashboards.

Main tasks:

- creating dashboards;
- modifying dashboards;
- arranging widgets;
- saving configurations;
- working with filters;
- owning user views.

### Alerting

Responsible for:

- notification rules;
- evaluating conditions;
- creating alerts;
- the alert lifecycle;
- interacting with future notification services.

## Planned Support Contexts

The future RAG chat includes `Support` (conversations, generations, feedback, and orchestration)
and `KnowledgeBase` (sources, indexing, and retrieval) within the analytics service.
Their contract and boundaries are defined in the [RAG specification](rag-support-chat.md) and
[ADR-019](12-architecture-decisions.md#adr-019--rag-support-chat).

This is a target design, not a description of implemented modules. During implementation, Workspace gains
the `support.use` capability for all existing roles; the current set of 8 permissions above remains
a description of the existing RBAC. Conversation access is additionally restricted by conversation ownership.

## Context Interaction Rules

One bounded context must not directly use another's internal models.

Interaction must take place through:

- application contracts;
- public services;
- events;
- read models;
- integration interfaces.

## Extraction into a Separate Service

A bounded context may become a separate microservice when there is:

- a distinct load profile;
- a separate team;
- a separate lifecycle;
- distinct technology requirements;
- a stable domain boundary;
- a need for independent scaling.
