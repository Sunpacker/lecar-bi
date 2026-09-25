import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import WorkspaceAccessPage from './page'
import { getSession } from '../../../../src/features/auth/model/session'
import { workspaceGateway } from '../../../../src/features/workspace/api/workspace-gateway'
import { redirect } from 'next/navigation'

vi.mock('next/navigation', () => ({
  redirect: vi.fn(() => {
    throw new Error('NEXT_REDIRECT')
  }),
}))

vi.mock('../../../../src/features/auth/model/session', () => ({
  getSession: vi.fn(),
}))

vi.mock('../../../../src/features/workspace/api/workspace-gateway', () => ({
  workspaceGateway: {
    getCurrentWorkspace: vi.fn(),
  },
}))

vi.mock('../../../../src/features/workspace/ui/workspace-member-list', () => ({
  WorkspaceMemberList: vi.fn(({ workspaceId, currentUserId }) => (
    <div data-testid="workspace-member-list">
      Members for {workspaceId} (current: {currentUserId})
    </div>
  )),
}))

describe('WorkspaceAccessPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('redirects to /login when user is not authenticated', async () => {
    vi.mocked(getSession).mockResolvedValue(null)

    await expect(WorkspaceAccessPage()).rejects.toThrow('NEXT_REDIRECT')
    expect(redirect).toHaveBeenCalledWith('/login')
  })

  it('renders access-denied state when user lacks workspace.members.manage capability', async () => {
    vi.mocked(getSession).mockResolvedValue({
      userId: 'user-viewer',
      email: 'viewer@example.com',
      name: 'Viewer User',
      token: 'mock-token',
      expiresAt: Date.now() + 10000,
    })

    vi.mocked(workspaceGateway.getCurrentWorkspace).mockResolvedValue({
      user: { id: 'user-viewer', email: 'viewer@example.com', name: 'Viewer User' },
      workspace: {
        id: 'ws-1',
        name: 'Workspace',
        slug: 'workspace',
        role: 'viewer',
        capabilities: ['analytics.view', 'dashboards.view'],
      },
    })

    const ui = await WorkspaceAccessPage()
    render(ui)

    expect(
      screen.getByRole('heading', { level: 1, name: 'Управление доступом' }),
    ).toBeDefined()
    expect(screen.getByTestId('access-denied')).toBeDefined()
    expect(screen.queryByTestId('workspace-member-list')).toBeNull()
  })

  it('renders WorkspaceMemberList when user has workspace.members.manage capability', async () => {
    vi.mocked(getSession).mockResolvedValue({
      userId: 'user-owner',
      email: 'owner@example.com',
      name: 'Owner User',
      token: 'mock-token',
      expiresAt: Date.now() + 10000,
    })

    vi.mocked(workspaceGateway.getCurrentWorkspace).mockResolvedValue({
      user: { id: 'user-owner', email: 'owner@example.com', name: 'Owner User' },
      workspace: {
        id: 'ws-1',
        name: 'Workspace',
        slug: 'workspace',
        role: 'owner',
        capabilities: ['workspace.members.manage', 'analytics.view'],
      },
    })

    const ui = await WorkspaceAccessPage()
    render(ui)

    expect(
      screen.getByRole('heading', { level: 1, name: 'Управление доступом' }),
    ).toBeDefined()
    expect(screen.queryByTestId('access-denied')).toBeNull()
    expect(screen.getByTestId('workspace-member-list')).toBeDefined()
    expect(screen.getByText('Members for ws-1 (current: user-owner)')).toBeDefined()
  })
})
