import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import SuppliersPage from './page'
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

vi.mock('../../../src/features/supplier-analytics/ui/supplier-tabs-container', () => ({
  SupplierTabsContainer: vi.fn(({ userId, workspaceId }) => (
    <div data-testid="supplier-tabs-container">
      Supplier Tabs: {userId} - {workspaceId}
    </div>
  )),
}))

describe('SuppliersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('redirects to /login when user is not authenticated', async () => {
    vi.mocked(getSession).mockResolvedValue(null)

    await expect(SuppliersPage()).rejects.toThrow('NEXT_REDIRECT')

    expect(redirect).toHaveBeenCalledWith('/login')
  })

  it('renders page header and SupplierTabsContainer with workspace id', async () => {
    vi.mocked(getSession).mockResolvedValue({
      userId: 'user-77',
      email: 'user@example.com',
      name: 'User',
      expiresAt: Date.now() + 10000,
    })

    vi.mocked(workspaceGateway.getCurrentWorkspace).mockResolvedValue({
      user: {
        id: 'user-77',
        email: 'user@example.com',
        name: 'User',
      },
      workspace: {
        id: 'ws-77',
        name: 'Supplier Test Workspace',
        slug: 'supplier-test',
        role: 'owner',
        capabilities: ['analytics.view'],
      },
    })

    const ui = await SuppliersPage()
    render(ui)

    expect(
      screen.getByRole('heading', { level: 1, name: 'Аналитика поставщиков' }),
    ).toBeDefined()
    expect(screen.getByTestId('supplier-tabs-container')).toBeDefined()
    expect(screen.getByText('Supplier Tabs: user-77 - ws-77')).toBeDefined()
  })
})
