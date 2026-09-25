import { describe, expect, it, vi, beforeEach } from 'vitest'
import { authGateway } from './auth-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    POST: vi.fn(),
  },
}))

describe('authGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('authenticates user successfully on valid credentials', async () => {
    const mockUser = {
      id: 'user-1',
      email: 'elena@autobi.internal',
      name: 'Elena Rostova',
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: { user: mockUser, token: 'test-token' } as any,
      response: { status: 200 } as Response,
      error: undefined,
    })

    const result = await authGateway.login({
      email: 'elena@autobi.internal',
      password: 'password123',
    })

    expect(result).toEqual({ user: mockUser, token: 'test-token' })
    expect(analyticsClient.POST).toHaveBeenCalledWith('/auth/login', {
      body: {
        email: 'elena@autobi.internal',
        password: 'password123',
      },
    })
  })

  it('throws custom error on failed login', async () => {
    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: undefined,
      response: { status: 401 } as Response,
      error: { message: 'Invalid credentials', code: 'INVALID_CREDENTIALS' },
    })

    await expect(
      authGateway.login({
        email: 'wrong@autobi.internal',
        password: 'bad',
      }),
    ).rejects.toThrow('Invalid credentials')
  })
})
