import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { Sidebar, NAVIGATION_ITEMS } from './sidebar'

let mockPathname = '/imports'
vi.mock('next/navigation', () => ({
  usePathname: () => mockPathname,
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

  it('includes Настройки with href /settings in NAVIGATION_ITEMS as enabled without badge', () => {
    const settingsItem = NAVIGATION_ITEMS.find((item) => item.href === '/settings')
    expect(settingsItem).toBeDefined()
    expect(settingsItem?.name).toBe('Настройки')
    expect(settingsItem?.disabled).toBeUndefined()
    expect(settingsItem?.badge).toBeUndefined()
  })

  it('does NOT have separate Доступ item in NAVIGATION_ITEMS', () => {
    const accessItem = NAVIGATION_ITEMS.find((item) => item.href === '/settings/access')
    expect(accessItem).toBeUndefined()
  })

  it('renders active Импорт данных navigation link and enabled Настройки link', () => {
    mockPathname = '/imports'
    render(<Sidebar />)
    const link = screen.getByRole('link', { name: /Импорт данных/i })
    expect(link).toBeDefined()
    expect(link.getAttribute('href')).toBe('/imports')

    const settingsLink = screen.getByRole('link', { name: /Настройки/i })
    expect(settingsLink).toBeDefined()
    expect(settingsLink.getAttribute('href')).toBe('/settings')
    expect(settingsLink.className).not.toContain('bg-emerald-500/10')
  })

  it('highlights Настройки when pathname starts with /settings', () => {
    mockPathname = '/settings/access'
    render(<Sidebar />)
    const settingsLink = screen.getByRole('link', { name: /Настройки/i })
    expect(settingsLink).toBeDefined()
    expect(settingsLink.className).toContain('bg-emerald-500/10')
  })
})
