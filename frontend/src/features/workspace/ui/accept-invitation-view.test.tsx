import React from 'react'
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { AcceptInvitationView } from './accept-invitation-view'
import { workspaceGateway } from '../api/workspace-gateway'

const mockPush = vi.fn()
const mockRefresh = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
    refresh: mockRefresh,
  }),
}))

vi.mock('../api/workspace-gateway', () => ({
  workspaceGateway: {
    getInvitationDetails: vi.fn(),
    acceptInvitation: vi.fn(),
  },
}))

describe('AcceptInvitationView', () => {
  const originalFetch = global.fetch

  beforeEach(() => {
    vi.clearAllMocks()
    global.fetch = vi.fn().mockResolvedValue({ ok: true })
  })

  afterEach(() => {
    global.fetch = originalFetch
  })

  it('renders existing user view and accepts invitation', async () => {
    vi.mocked(workspaceGateway.getInvitationDetails).mockResolvedValueOnce({
      email: 'existing@autobi.local',
      workspace_name: 'Super Workspace',
      role: 'member',
      is_expired: false,
      is_existing_user: true,
    })

    vi.mocked(workspaceGateway.acceptInvitation).mockResolvedValueOnce({
      user: { id: 'u-99', email: 'existing@autobi.local', name: 'Existing User' },
      workspace_id: 'ws-1',
      token: 'tok-abc',
    })

    render(<AcceptInvitationView token="test-token-123" />)

    await waitFor(() => {
      expect(screen.getByText('Super Workspace')).toBeDefined()
      expect(screen.getByText('Принять приглашение')).toBeDefined()
    })

    fireEvent.click(screen.getByRole('button', { name: /Принять приглашение/i }))

    await waitFor(() => {
      expect(workspaceGateway.acceptInvitation).toHaveBeenCalledWith(
        'test-token-123',
        undefined,
      )
    })
  })

  it('renders new user registration form and submits credentials', async () => {
    vi.mocked(workspaceGateway.getInvitationDetails).mockResolvedValueOnce({
      email: 'newuser@autobi.local',
      workspace_name: 'Super Workspace',
      role: 'viewer',
      is_expired: false,
      is_existing_user: false,
    })

    vi.mocked(workspaceGateway.acceptInvitation).mockResolvedValueOnce({
      user: { id: 'u-100', email: 'newuser@autobi.local', name: 'New User' },
      workspace_id: 'ws-1',
      token: 'tok-xyz',
    })

    render(<AcceptInvitationView token="test-token-456" />)

    await waitFor(() => {
      expect(screen.getByLabelText('Ваше имя')).toBeDefined()
      expect(screen.getByLabelText('Придумайте пароль')).toBeDefined()
    })

    fireEvent.change(screen.getByLabelText('Ваше имя'), {
      target: { value: 'Ivan Petrov' },
    })
    fireEvent.change(screen.getByLabelText('Придумайте пароль'), {
      target: { value: 'SecurePass123!' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Зарегистрироваться и принять' }))

    await waitFor(() => {
      expect(workspaceGateway.acceptInvitation).toHaveBeenCalledWith('test-token-456', {
        name: 'Ivan Petrov',
        password: 'SecurePass123!',
      })
    })
  })

  it('displays error if invitation is expired or not found', async () => {
    vi.mocked(workspaceGateway.getInvitationDetails).mockRejectedValueOnce(
      new Error('Приглашение не найдено или срок его действия истёк'),
    )

    render(<AcceptInvitationView token="invalid-token" />)

    await waitFor(() => {
      expect(screen.getByText('Приглашение недоступно')).toBeDefined()
    })
  })
})
