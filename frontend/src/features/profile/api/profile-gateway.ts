import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type UserProfile = components['schemas']['UserResponse']

export class ProfileApiError extends Error {
  constructor(
    message: string,
    public readonly code?: string,
    public readonly status?: number,
  ) {
    super(message)
    this.name = 'ProfileApiError'
  }
}

export const profileGateway = {
  async updateProfile(name: string): Promise<UserProfile> {
    const { data, error, response } = await analyticsClient.PATCH('/me', {
      body: { name },
    })

    if (error || !data) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new ProfileApiError(
        errPayload?.message || 'Не удалось обновить профиль',
        errPayload?.code,
        response?.status,
      )
    }

    return data
  },

  async changePassword(
    currentPassword: string,
    newPassword: string,
    newPasswordConfirmation: string,
  ): Promise<void> {
    const { error, response } = await analyticsClient.POST('/me/password', {
      body: {
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation,
      },
    })

    if (error) {
      const errPayload = error as { message?: string; code?: string } | undefined
      throw new ProfileApiError(
        errPayload?.message || 'Не удалось изменить пароль',
        errPayload?.code,
        response?.status,
      )
    }
  },
}
