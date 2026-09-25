import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type LoginRequest = components['schemas']['LoginRequest']
export type LoginResponse = components['schemas']['LoginResponse']
export type AuthenticatedUser = components['schemas']['UserResponse']

export const authGateway = {
  async login(
    credentials: LoginRequest,
  ): Promise<{ user: AuthenticatedUser; token: string }> {
    const { data, error, response } = await analyticsClient.POST('/auth/login', {
      body: credentials,
    })

    if (error || !data) {
      const message =
        typeof error === 'object' && error !== null && 'message' in error
          ? (error as { message: string }).message
          : 'Ошибка аутентификации'
      const code =
        typeof error === 'object' && error !== null && 'code' in error
          ? (error as { code: string }).code
          : 'AUTH_ERROR'

      const customError = new Error(message)
      ;(customError as Error & { code?: string; status?: number }).code = code
      ;(customError as Error & { code?: string; status?: number }).status =
        response.status
      throw customError
    }

    return { user: data.user, token: (data as any).token }
  },
}
