import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { WorkspaceInvitationsList } from './workspace-invitations-list'
import { workspaceGateway, type Invitation } from '../api/workspace-gateway'

vi.mock('../api/workspace-gateway', () => ({
  workspaceGateway: {
    listInvitations: vi.fn(),
    resendInvitation: vi.fn(),
    cancelInvitation: vi.fn(),
  },
}))

describe('WorkspaceInvitationsList', () => {
  const mockInvitations: Invitation[] = [
    {
      id: 'inv-1',
      workspace_id: 'ws-1',
      email: 'newuser@autobi.local',
      role: 'member',
      status: 'pending',
      expires_at: '2026-10-02T10:00:00Z',
      created_at: '2026-09-25T10:00:00Z',
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders pending invitations table', async () => {
    vi.mocked(workspaceGateway.listInvitations).mockResolvedValueOnce(mockInvitations)

    render(<WorkspaceInvitationsList workspaceId="ws-1" canManage={true} />)

    await waitFor(() => {
      expect(screen.getByText('newuser@autobi.local')).toBeDefined()
      expect(screen.getByText('Участник')).toBeDefined()
    })
  })

  it('resends an invitation', async () => {
    vi.mocked(workspaceGateway.listInvitations).mockResolvedValueOnce(mockInvitations)
    vi.mocked(workspaceGateway.resendInvitation).mockResolvedValueOnce({
      ...mockInvitations[0],
      expires_at: '2026-10-02T12:00:00Z',
    })

    render(<WorkspaceInvitationsList workspaceId="ws-1" canManage={true} />)

    await waitFor(() => {
      expect(screen.getByText('newuser@autobi.local')).toBeDefined()
    })

    const resendBtn = screen.getByTitle('Отправить ссылку повторно')
    fireEvent.click(resendBtn)

    await waitFor(() => {
      expect(workspaceGateway.resendInvitation).toHaveBeenCalledWith('ws-1', 'inv-1')
      expect(screen.getByRole('status').textContent).toContain('повторно отправлено')
    })
  })

  it('cancels an invitation', async () => {
    vi.mocked(workspaceGateway.listInvitations).mockResolvedValueOnce(mockInvitations)
    vi.mocked(workspaceGateway.cancelInvitation).mockResolvedValueOnce(undefined)

    render(<WorkspaceInvitationsList workspaceId="ws-1" canManage={true} />)

    await waitFor(() => {
      expect(screen.getByText('newuser@autobi.local')).toBeDefined()
    })

    const cancelBtn = screen.getByTitle('Отозвать приглашение')
    fireEvent.click(cancelBtn)

    await waitFor(() => {
      expect(workspaceGateway.cancelInvitation).toHaveBeenCalledWith('ws-1', 'inv-1')
      expect(screen.getByRole('status').textContent).toContain('отменено')
      expect(screen.queryByText('newuser@autobi.local')).toBeNull()
    })
  })
})
