import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { ProfileForm } from './profile-form'
import { profileGateway } from '../api/profile-gateway'

const mockRefresh = vi.fn()
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    refresh: mockRefresh,
  }),
}))

vi.mock('../api/profile-gateway', () => ({
  profileGateway: {
    updateProfile: vi.fn(),
  },
}))

describe('ProfileForm', () => {
  const initialUser = {
    id: 'u-1',
    email: 'admin@autobi.local',
    name: 'Admin User',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders initial user info with disabled email', () => {
    render(<ProfileForm initialUser={initialUser} />)

    const emailInput = screen.getByLabelText('Email') as HTMLInputElement
    expect(emailInput.value).toBe('admin@autobi.local')
    expect(emailInput.disabled).toBe(true)

    const nameInput = screen.getByLabelText('Имя пользователя') as HTMLInputElement
    expect(nameInput.value).toBe('Admin User')
  })

  it('submits name change and triggers refresh', async () => {
    vi.mocked(profileGateway.updateProfile).mockResolvedValueOnce({
      id: 'u-1',
      email: 'admin@autobi.local',
      name: 'Super Admin',
    })

    render(<ProfileForm initialUser={initialUser} />)

    const nameInput = screen.getByLabelText('Имя пользователя')
    fireEvent.change(nameInput, { target: { value: 'Super Admin' } })

    const submitBtn = screen.getByRole('button', { name: 'Сохранить изменения' })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(profileGateway.updateProfile).toHaveBeenCalledWith('Super Admin')
      expect(screen.getByRole('status').textContent).toContain('Профиль успешно обновлён')
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })
  })
})
