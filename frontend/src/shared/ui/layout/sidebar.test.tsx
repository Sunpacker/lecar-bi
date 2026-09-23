import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { Sidebar, NAVIGATION_ITEMS } from './sidebar'
import { WorkspaceAccessProvider } from '../../../features/workspace/ui/workspace-access-provider'

vi.mock('next/navigation', () => ({
  usePathname: () => '/imports',
}))

describe('Sidebar navigation', () => {
  it('includes Импорт данных with href /imports in NAVIGATION_ITEMS', () => {
    const importItem = NAVIGATION_ITEMS.find((item) => item.href === '/imports')
    expect(importItem).toBeDefined()
    expect(importItem?.name).toBe('Импорт данных')
    expect(importItem?.disabled).toBeUndefined()
  })

  it('includes Поставщики with href /suppliers in NAVIGATION_ITEMS as enabled', () => {
    const suppliersItem = NAVIGATION_ITEMS.find((item) => item.href === '/suppliers')
    expect(suppliersItem).toBeDefined()
    expect(suppliersItem?.name).toBe('Поставщики')
    expect(suppliersItem?.disabled).toBeUndefined()
  })

  it('includes Доступ with href /settings/access guarded by workspace.members.manage', () => {
    const accessItem = NAVIGATION_ITEMS.find((item) => item.href === '/settings/access')
    expect(accessItem).toBeDefined()
    expect(accessItem?.name).toBe('Доступ')
    expect(accessItem?.requiredCapability).toBe('workspace.members.manage')
  })

  it('renders active Импорт данных navigation link and enabled Поставщики link', () => {
    render(<Sidebar />)
    const link = screen.getByRole('link', { name: /Импорт данных/i })
    expect(link).toBeDefined()
    expect(link.getAttribute('href')).toBe('/imports')

    const suppliersLink = screen.getByRole('link', { name: /Поставщики/i })
    expect(suppliersLink).toBeDefined()
    expect(suppliersLink.getAttribute('href')).toBe('/suppliers')
  })

  it('does NOT render Доступ link when user lacks workspace.members.manage capability', () => {
    render(
      <WorkspaceAccessProvider capabilities={['analytics.view', 'dashboards.view']}>
        <Sidebar />
      </WorkspaceAccessProvider>,
    )
    expect(screen.queryByRole('link', { name: /Доступ/i })).toBeNull()
  })

  it('renders Доступ link when user has workspace.members.manage capability', () => {
    render(
      <WorkspaceAccessProvider capabilities={['workspace.members.manage']}>
        <Sidebar />
      </WorkspaceAccessProvider>,
    )
    const accessLink = screen.getByRole('link', { name: /Доступ/i })
    expect(accessLink).toBeDefined()
    expect(accessLink.getAttribute('href')).toBe('/settings/access')
  })
})
