# 03. Frontend Architecture — Next.js

## Frontend Role

Next.js is AutoBI's user-facing web application.

Its purpose is to provide a fast, interactive, and convenient interface for working with analytics.

## Required UI Stack

The interface must be built using **shadcn/ui together with Tailwind CSS**.

- shadcn/ui is the foundation for reusable UI components.
- Tailwind CSS is the primary tool for styling and responsive layouts.
- Common UI components belong in shared UI and are reused across functional areas.

This stack choice is recorded in [ADR-016](12-architecture-decisions.md#adr-016--shadcnui-and-tailwind-css).

## Main Responsibilities

The frontend is responsible for:

- displaying analytical data;
- interactive dashboards;
- charting;
- tables;
- global and local filters;
- drill-down flows;
- the dashboard builder;
- client state;
- user navigation;
- SSR and server-side data fetching;
- frontend session handling;
- frontend-specific data mapping.

## Business Logic Constraints

The frontend must not define domain rules.

If a metric affects business interpretation, it must be calculated on the backend.

Examples of such rules:

- determining critical stock levels;
- calculating days of stock;
- ABC/XYZ classification;
- determining overstock;
- calculating KPIs;
- alert triggering rules.

The frontend receives precomputed values and is responsible for their presentation.

## Feature-Oriented Structure

The frontend should be organized by functional area, rather than literally reproducing backend DDD.

Possible major areas:

- dashboards;
- sales analytics;
- inventory analytics;
- supplier analytics;
- datasets;
- alerts;
- shared UI;
- shared API client;
- shared utilities.

## API Access

The frontend must access the backend through a typed client generated from the OpenAPI contract.

This helps:

- reduce type mismatches;
- control API changes;
- simplify refactoring;
- expose contract errors during development.

## BFF

Next.js may act as a thin BFF.

The BFF may:

- handle cookies and sessions;
- make server-side requests to Laravel;
- hide the backend's internal address;
- combine several technical requests for UI needs;
- perform frontend-specific transformations.

The BFF must not:

- implement business rules;
- calculate analytical metrics;
- determine domain statuses;
- replace the backend Application Layer.

## Authorization and Access Control (RBAC)

Frontend access control follows **capabilities-driven UX**:

- The frontend uses the list of capabilities (`capabilities`) returned by the backend in the `WorkspaceResponse` contract (endpoints `/workspaces`, `/workspaces/current`, `/workspaces/{id}`).
- The frontend **never derives permissions from role strings** (`owner`, `member`, `viewer`). The domain role remains exclusively in the backend Workspace Domain.
- The application uses the `WorkspaceAccessProvider` React context and the `useWorkspaceAccess` hook, which provides `hasCapability(requiredCapability)`.
- The **fail-closed** principle is implemented: if the access context is absent or the capabilities list is missing, the check returns `false`, preventing unauthorized mutations.
- Hiding or disabling controls (dashboard create/delete buttons, alert create/toggle buttons, import uploads, preset menus) is purely a UX optimization to keep the interface clear. The backend remains the sole authoritative source of access control (HTTP `403 INSUFFICIENT_CAPABILITY`).
- The `/settings/access` page displays the member list and role change form only when `workspace.members.manage` is available.

## UI Component Catalog

Storybook with the Next.js Vite framework is the isolated catalog for the existing shadcn/Tailwind primitives and shared BI/form compositions. Stories import components directly, use fixed demo data, and require neither backend nor authentication. The theme toolbar applies the light/dark class to the preview document so portal content follows the selected theme. Shared compositions live in `frontend/src/shared/ui/` and receive formatted values, rows and callbacks; sales formatting and API pagination mapping remain inside the sales feature.
