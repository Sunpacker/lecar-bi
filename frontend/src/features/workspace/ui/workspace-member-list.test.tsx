import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { WorkspaceMemberList } from './workspace-member-list'
import {
  workspaceGateway,
  WorkspaceApiError,
  type WorkspaceMember,
} from '../api/workspace-gateway'

const mockRefresh = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    refresh: mockRefresh,
  }),
}))

vi.mock('../api/workspace-gateway', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../api/workspace-gateway')>()
  return {
    ...actual,
    workspaceGateway: {
      ...actual.workspaceGateway,
      listWorkspaceMembers: vi.fn(),
      changeMemberRole: vi.fn(),
    },
  }
})

describe('WorkspaceMemberList', () => {
  const mockMembers: WorkspaceMember[] = [
    {
      user: { id: 'u-1', email: 'owner@autobi.local', name: 'Owner User' },
      role: 'owner',
    },
    {
      user: { id: 'u-2', email: 'member@autobi.local', name: 'Member User' },
      role: 'member',
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders loading state and then members list', async () => {
    vi.mocked(workspaceGateway.listWorkspaceMembers).mockResolvedValueOnce(mockMembers)

    render(<WorkspaceMemberList workspaceId="ws-1" currentUserId="u-1" />)

    expect(screen.getByTestId('members-loading')).toBeDefined()

    await waitFor(() => {
      expect(screen.getByText('Owner User')).toBeDefined()
      expect(screen.getByText('Member User')).toBeDefined()
    })

    expect(screen.getByText('owner@autobi.local')).toBeDefined()
    expect(screen.getByText('member@autobi.local')).toBeDefined()

    const select = screen.getByLabelText('Роль пользователя Owner User')
    expect(select).toBeDefined()
    expect((select as HTMLSelectElement).value).toBe('owner')
  })

  it('successfully changes member role and updates UI', async () => {
    vi.mocked(workspaceGateway.listWorkspaceMembers).mockResolvedValueOnce(mockMembers)
    vi.mocked(workspaceGateway.changeMemberRole).mockResolvedValueOnce({
      user: { id: 'u-2', email: 'member@autobi.local', name: 'Member User' },
      role: 'viewer',
    })

    render(<WorkspaceMemberList workspaceId="ws-1" currentUserId="u-1" />)

    await waitFor(() => {
      expect(screen.getByText('Member User')).toBeDefined()
    })

    const select = screen.getByLabelText('Роль пользователя Member User')
    fireEvent.change(select, { target: { value: 'viewer' } })

    await waitFor(() => {
      expect(workspaceGateway.changeMemberRole).toHaveBeenCalledWith(
        'ws-1',
        'u-2',
        'viewer',
      )
      expect(screen.getByText(/успешно изменена на «Наблюдатель»/)).toBeDefined()
    })
  })

  it('calls router.refresh when changing current user role', async () => {
    vi.mocked(workspaceGateway.listWorkspaceMembers).mockResolvedValueOnce(mockMembers)
    vi.mocked(workspaceGateway.changeMemberRole).mockResolvedValueOnce({
      user: { id: 'u-1', email: 'owner@autobi.local', name: 'Owner User' },
      role: 'member',
    })

    render(<WorkspaceMemberList workspaceId="ws-1" currentUserId="u-1" />)

    await waitFor(() => {
      expect(screen.getByText('Owner User')).toBeDefined()
    })

    const select = screen.getByLabelText('Роль пользователя Owner User')
    fireEvent.change(select, { target: { value: 'member' } })

    await waitFor(() => {
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })
  })

  it('displays user-friendly error when demoting last workspace owner (409)', async () => {
    vi.mocked(workspaceGateway.listWorkspaceMembers).mockResolvedValueOnce(mockMembers)
    vi.mocked(workspaceGateway.changeMemberRole).mockRejectedValueOnce(
      new WorkspaceApiError(
        'Cannot demote the sole workspace owner',
        'LAST_WORKSPACE_OWNER',
        409,
      ),
    )

    render(<WorkspaceMemberList workspaceId="ws-1" currentUserId="u-1" />)

    await waitFor(() => {
      expect(screen.getByText('Owner User')).toBeDefined()
    })

    const select = screen.getByLabelText('Роль пользователя Owner User')
    fireEvent.change(select, { target: { value: 'viewer' } })

    await waitFor(() => {
      const alert = screen.getByRole('alert')
      expect(alert.textContent).toContain(
        'Нельзя понизить единственного владельца воркспейса.',
      )
    })

    // Select should remain unchanged
    expect((select as HTMLSelectElement).value).toBe('owner')
  })
})
