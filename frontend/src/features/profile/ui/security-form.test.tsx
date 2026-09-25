import React from 'react'
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { SecurityForm } from './security-form'
import { profileGateway } from '../api/profile-gateway'

const mockPush = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}))

vi.mock('../api/profile-gateway', () => ({
  profileGateway: {
    changePassword: vi.fn(),
  },
}))

describe('SecurityForm', () => {
  const originalFetch = global.fetch

  beforeEach(() => {
    vi.clearAllMocks()
    global.fetch = vi.fn().mockResolvedValue({ ok: true })
  })

  afterEach(() => {
    global.fetch = originalFetch
  })

  it('renders password change form with revocation warning', () => {
    render(<SecurityForm />)

    expect(screen.getByLabelText('Текущий пароль')).toBeDefined()
    expect(screen.getByLabelText('Новый пароль')).toBeDefined()
    expect(screen.getByLabelText('Подтверждение нового пароля')).toBeDefined()
    expect(screen.getByText(/Внимание: отзыв сессий/)).toBeDefined()
  })

  it('shows error if new password and confirmation do not match', async () => {
    render(<SecurityForm />)

    fireEvent.change(screen.getByLabelText('Текущий пароль'), {
      target: { value: 'OldPassword123!' },
    })
    fireEvent.change(screen.getByLabelText('Новый пароль'), {
      target: { value: 'NewPassword123!' },
    })
    fireEvent.change(screen.getByLabelText('Подтверждение нового пароля'), {
      target: { value: 'MismatchPassword123!' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Сменить пароль' }))

    await waitFor(() => {
      const alert = screen.getByRole('alert')
      expect(alert.textContent).toContain('Новый пароль и подтверждение не совпадают')
    })
    expect(profileGateway.changePassword).not.toHaveBeenCalled()
  })

  it('submits password change and redirects to login with reason', async () => {
    vi.mocked(profileGateway.changePassword).mockResolvedValueOnce(undefined)

    render(<SecurityForm />)

    fireEvent.change(screen.getByLabelText('Текущий пароль'), {
      target: { value: 'OldPassword123!' },
    })
    fireEvent.change(screen.getByLabelText('Новый пароль'), {
      target: { value: 'NewPassword123!' },
    })
    fireEvent.change(screen.getByLabelText('Подтверждение нового пароля'), {
      target: { value: 'NewPassword123!' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Сменить пароль' }))

    await waitFor(() => {
      expect(profileGateway.changePassword).toHaveBeenCalledWith(
        'OldPassword123!',
        'NewPassword123!',
        'NewPassword123!',
      )
      expect(mockPush).toHaveBeenCalledWith('/login?reason=password_changed')
    })
  })
})
