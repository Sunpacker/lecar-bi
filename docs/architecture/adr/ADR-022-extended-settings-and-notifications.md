# ADR-022: Extended Settings, Email Invitations, and Personal Notifications

## Status

Accepted (2026-09-25)

## Context

Previously, the AutoBI platform provided only basic member role management on `/settings/access` and browser theme switching. The following were missing:

- Personal profile (name) editing and password changes with active session revocation;
- Workspace renaming by the owner;
- Email invitations for new members with a link, expiration, and role selection;
- Personal notification read statuses and personal severity preferences (`info`, `warning`, `critical`).

## Decision

### 1. Frontend Settings Architecture

- Sidebar: the Settings item (`/settings`) is enabled, the Coming Soon badge is removed, and the separate Access item is removed from root navigation (moved to a settings tab). The section is highlighted on all child routes `/settings/*`.
- A shared `SettingsLayout` shell (`frontend/app/(dashboard)/settings/layout.tsx`) with tab subnavigation:
  - `/settings` — Profile (name, read-only email) and Appearance (light, dark, and system themes persisted in `autobi-theme`);
  - `/settings/security` — Password change (current, new, confirmation) with a session revocation warning and redirect to `/login?reason=password_changed`;
  - `/settings/workspace` — Workspace name (editable only by the owner with the `workspace.settings.manage` capability), read-only slug;
  - `/settings/access` — Member management, role changes with last-owner protection, a list of pending invitations (resend, revoke), and an invitation modal;
  - `/settings/notifications` — Personal severity preferences for the selected workspace.

### 2. Workspace Invitations

- Stored in PostgreSQL (`invitations`), fields: `id`, `workspace_id`, `email`, `role` (`member` | `viewer`), `status` (`pending`, `accepted`, `cancelled`), `token_hash`, `expires_at`, `created_at`.
- The link token is single-use (valid for 7 days). Only its SHA-256 hash is stored in the database.
- Email delivery uses the Laravel Mail `mail` queue (`WorkspaceInvitationMail`, `SendWorkspaceInvitationEmailJob`).
- Start the background worker with `php artisan queue:work redis --queue=default,mail --tries=3`.
- Public acceptance route: `/invite/[token]`. Displays details without authentication; registered users see an accept button, and new users see a name and password form. Account creation, workspace membership, and session token issuance are atomic.

### 3. Notification Center and Personal Settings

- `/notifications` page: pagination, filtering by unread status and severity, marking an individual notification as read, and a snapshot-based Mark All as Read operation (`up_to_notification_id`).
- Header: a `NotificationBell` component with an unread counter, refreshed on load, on focus return, and every 30 seconds while the tab is active.
- Personal tables in the Notification DB: `notification_reads` (user_id, notification_id, read_at) and `notification_preferences` (user_id, workspace_id, info, warning, critical).
- Inter-service communication: the Next.js BFF proxy checks the session and permissions, then calls Notification Service with `X-Server-Secret`, `X-User-Id`, and `X-Workspace-Id` headers. If Notification Service fails, the BFF client returns a graceful fallback so analytics is not blocked.
