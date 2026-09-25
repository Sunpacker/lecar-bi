import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { NotificationPreferencesForm } from './notification-preferences-form'
import { NotificationsList } from './notifications-list'
import { notificationsGateway } from '../api/notifications-gateway'

vi.mock('../api/notifications-gateway', () => ({
  notificationsGateway: {
    getPreferences: vi.fn(),
    updatePreferences: vi.fn(),
    listNotifications: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

describe('NotificationPreferencesForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders preferences checkboxes and updates preference', async () => {
    vi.mocked(notificationsGateway.getPreferences).mockResolvedValueOnce({
      info: true,
      warning: true,
      critical: true,
    })
    vi.mocked(notificationsGateway.updatePreferences).mockResolvedValueOnce({
      info: false,
      warning: true,
      critical: true,
    })

    render(<NotificationPreferencesForm />)

    await waitFor(() => {
      expect(screen.getByText('Критические события')).toBeDefined()
      expect(screen.getByText('Информационные события')).toBeDefined()
    })

    const infoCheckbox = screen.getByLabelText(
      /Информационные события/i,
    ) as HTMLInputElement
    expect(infoCheckbox.checked).toBe(true)

    fireEvent.click(infoCheckbox)
    expect(infoCheckbox.checked).toBe(false)

    fireEvent.click(screen.getByRole('button', { name: 'Сохранить настройки' }))

    await waitFor(() => {
      expect(notificationsGateway.updatePreferences).toHaveBeenCalledWith({
        info: false,
        warning: true,
        critical: true,
      })
      expect(screen.getByRole('status').textContent).toContain('успешно сохранены')
    })
  })
})

describe('NotificationsList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders notifications list and marks single notification read', async () => {
    vi.mocked(notificationsGateway.listNotifications).mockResolvedValueOnce({
      items: [
        {
          id: 'n-1',
          severity: 'critical',
          title: 'Stock Critical Alert',
          body: 'Product out of stock',
          occurred_at: '2026-09-25T12:00:00Z',
          read_at: null,
        },
      ],
      total: 1,
      page: 1,
      per_page: 20,
      unread_count: 1,
    })
    vi.mocked(notificationsGateway.markRead).mockResolvedValueOnce(true)

    render(<NotificationsList />)

    await waitFor(() => {
      expect(screen.getByText('Stock Critical Alert')).toBeDefined()
      expect(screen.getByText('Product out of stock')).toBeDefined()
    })

    const markBtn = screen.getByTitle('Отметить как прочитанное')
    fireEvent.click(markBtn)

    await waitFor(() => {
      expect(notificationsGateway.markRead).toHaveBeenCalledWith('n-1')
    })
  })

  it('marks all notifications read via snapshot', async () => {
    vi.mocked(notificationsGateway.listNotifications).mockResolvedValueOnce({
      items: [
        {
          id: 'n-1',
          severity: 'warning',
          title: 'Warning Alert',
          body: 'Low stock warning',
          occurred_at: '2026-09-25T12:00:00Z',
          read_at: null,
        },
      ],
      total: 1,
      page: 1,
      per_page: 20,
      unread_count: 1,
    })
    vi.mocked(notificationsGateway.markAllRead).mockResolvedValueOnce(1)

    render(<NotificationsList />)

    await waitFor(() => {
      expect(screen.getByText('Warning Alert')).toBeDefined()
    })

    const markAllBtn = screen.getByRole('button', { name: /Прочитать всё/i })
    fireEvent.click(markAllBtn)

    await waitFor(() => {
      expect(notificationsGateway.markAllRead).toHaveBeenCalledWith('n-1')
    })
  })
})
