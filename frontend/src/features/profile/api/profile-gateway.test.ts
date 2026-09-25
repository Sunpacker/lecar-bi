import { describe, expect, it, vi, beforeEach } from 'vitest'
import { profileGateway, ProfileApiError } from './profile-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    PATCH: vi.fn(),
    POST: vi.fn(),
  },
}))

describe('profileGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('updates profile name successfully', async () => {
    const updatedUser = { id: 'u-1', email: 'user@autobi.local', name: 'New Name' }
    vi.mocked(analyticsClient.PATCH).mockResolvedValueOnce({
      data: updatedUser,
      error: undefined,
      response: { status: 200 } as Response,
    })

    const result = await profileGateway.updateProfile('New Name')
    expect(result).toEqual(updatedUser)
    expect(analyticsClient.PATCH).toHaveBeenCalledWith('/me', {
      body: { name: 'New Name' },
    })
  })

  it('throws ProfileApiError on update error', async () => {
    vi.mocked(analyticsClient.PATCH).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Invalid name', code: 'INVALID_ARGUMENT' },
      response: { status: 422 } as Response,
    })

    await expect(profileGateway.updateProfile('')).rejects.toThrow(ProfileApiError)
  })

  it('changes password successfully', async () => {
    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: { message: 'Password changed successfully' },
      error: undefined,
      response: { status: 200 } as Response,
    })

    await expect(
      profileGateway.changePassword('OldPass123!', 'NewPass456!', 'NewPass456!'),
    ).resolves.toBeUndefined()

    expect(analyticsClient.POST).toHaveBeenCalledWith('/me/password', {
      body: {
        current_password: 'OldPass123!',
        new_password: 'NewPass456!',
        new_password_confirmation: 'NewPass456!',
      },
    })
  })

  it('throws ProfileApiError on wrong current password', async () => {
    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: undefined,
      error: {
        message: 'Текущий пароль указан неверно',
        code: 'INVALID_CURRENT_PASSWORD',
      },
      response: { status: 422 } as Response,
    })

    await expect(
      profileGateway.changePassword('WrongPass', 'NewPass456!', 'NewPass456!'),
    ).rejects.toThrow(ProfileApiError)
  })
})
