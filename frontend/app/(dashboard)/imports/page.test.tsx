import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import ImportsPage from './page'
import { getSession } from '../../../src/features/auth/model/session'
import { workspaceGateway } from '../../../src/features/workspace/api/workspace-gateway'
import { redirect } from 'next/navigation'

vi.mock('next/navigation', () => ({
  redirect: vi.fn(() => {
    throw new Error('NEXT_REDIRECT')
  }),
}))

vi.mock('../../../src/features/auth/model/session', () => ({
  getSession: vi.fn(),
}))

vi.mock('../../../src/features/workspace/api/workspace-gateway', () => ({
  workspaceGateway: {
    getCurrentWorkspace: vi.fn(),
  },
}))

vi.mock('../../../src/features/data-ingestion/ui/data-ingestion-view', () => ({
  DataIngestionView: vi.fn(({ userId, workspaceId }) => (
    <div data-testid="data-ingestion-view">
      Ingestion View: {userId} - {workspaceId}
    </div>
  )),
}))

describe('ImportsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('redirects to /login when user is not authenticated', async () => {
    vi.mocked(getSession).mockResolvedValue(null)

    await expect(ImportsPage()).rejects.toThrow('NEXT_REDIRECT')

    expect(redirect).toHaveBeenCalledWith('/login')
  })

  it('renders page header and DataIngestionView with workspace id', async () => {
    vi.mocked(getSession).mockResolvedValue({
      userId: 'user-42',
      email: 'user@example.com',
      name: 'User',
      token: 'mock-token',
      expiresAt: Date.now() + 10000,
    })

    vi.mocked(workspaceGateway.getCurrentWorkspace).mockResolvedValue({
      user: {
        id: 'user-42',
        email: 'user@example.com',
        name: 'User',
      },
      workspace: {
        id: 'ws-99',
        name: 'Main Workspace',
        slug: 'main-workspace',
        role: 'owner',
        capabilities: ['imports.view', 'imports.manage'],
      },
    })

    const ui = await ImportsPage()
    render(ui)

    expect(screen.getByRole('heading', { level: 1, name: 'Импорт данных' })).toBeDefined()
    expect(screen.getByTestId('data-ingestion-view')).toBeDefined()
    expect(screen.getByText('Ingestion View: user-42 - ws-99')).toBeDefined()
  })
})
