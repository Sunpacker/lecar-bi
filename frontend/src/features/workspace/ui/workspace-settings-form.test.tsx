import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { WorkspaceSettingsForm } from './workspace-settings-form'
import { workspaceGateway, type Workspace } from '../api/workspace-gateway'

const mockRefresh = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    refresh: mockRefresh,
  }),
}))

vi.mock('../api/workspace-gateway', () => ({
  workspaceGateway: {
    renameWorkspace: vi.fn(),
  },
}))

describe('WorkspaceSettingsForm', () => {
  const mockWorkspace: Workspace = {
    id: 'ws-1',
    name: 'AutoParts Retail',
    slug: 'autoparts-retail',
    role: 'owner',
    capabilities: ['workspace.settings.manage', 'workspace.members.manage'],
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders workspace details with slug disabled', () => {
    render(<WorkspaceSettingsForm workspace={mockWorkspace} canManage={true} />)

    const slugInput = screen.getByLabelText('Идентификатор (Slug)') as HTMLInputElement
    expect(slugInput.value).toBe('autoparts-retail')
    expect(slugInput.disabled).toBe(true)

    const nameInput = screen.getByLabelText('Название пространства') as HTMLInputElement
    expect(nameInput.value).toBe('AutoParts Retail')
    expect(nameInput.disabled).toBe(false)
  })

  it('disables name input and hides save button when canManage is false', () => {
    render(<WorkspaceSettingsForm workspace={mockWorkspace} canManage={false} />)

    const nameInput = screen.getByLabelText('Название пространства') as HTMLInputElement
    expect(nameInput.disabled).toBe(true)
    expect(screen.queryByRole('button', { name: 'Сохранить изменения' })).toBeNull()
    expect(screen.getByText(/Только владелец рабочего пространства/)).toBeDefined()
  })

  it('successfully renames workspace and calls refresh', async () => {
    vi.mocked(workspaceGateway.renameWorkspace).mockResolvedValueOnce({
      ...mockWorkspace,
      name: 'AutoParts Global',
    })

    render(<WorkspaceSettingsForm workspace={mockWorkspace} canManage={true} />)

    const nameInput = screen.getByLabelText('Название пространства')
    fireEvent.change(nameInput, { target: { value: 'AutoParts Global' } })

    const submitBtn = screen.getByRole('button', { name: 'Сохранить изменения' })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(workspaceGateway.renameWorkspace).toHaveBeenCalledWith(
        'ws-1',
        'AutoParts Global',
      )
      expect(screen.getByRole('status').textContent).toContain('успешно изменено')
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })
  })
})
