import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { Sidebar, NAVIGATION_ITEMS } from './sidebar'

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

  it('renders active Импорт данных navigation link', () => {
    render(<Sidebar />)
    const link = screen.getByRole('link', { name: /Импорт данных/i })
    expect(link).toBeDefined()
    expect(link.getAttribute('href')).toBe('/imports')
  })
})
