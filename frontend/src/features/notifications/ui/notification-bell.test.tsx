import React from 'react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { NotificationBell } from './notification-bell'
import { notificationsGateway } from '../api/notifications-gateway'

vi.mock('../api/notifications-gateway', () => ({
  notificationsGateway: {
    getUnreadCount: vi.fn(),
  },
}))

describe('NotificationBell', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders bell link pointing to /notifications with 0 unread', async () => {
    vi.mocked(notificationsGateway.getUnreadCount).mockResolvedValueOnce(0)

    render(<NotificationBell />)

    const bell = screen.getByTestId('notification-bell')
    expect(bell).toBeDefined()
    expect(bell.getAttribute('href')).toBe('/notifications')
    expect(screen.queryByTestId('notification-badge')).toBeNull()
  })

  it('displays badge with unread count when unreadCount > 0', async () => {
    vi.mocked(notificationsGateway.getUnreadCount).mockResolvedValueOnce(5)

    render(<NotificationBell />)

    await waitFor(() => {
      const badge = screen.getByTestId('notification-badge')
      expect(badge).toBeDefined()
      expect(badge.textContent).toBe('5')
    })
  })

  it('displays 99+ when unreadCount > 99', async () => {
    vi.mocked(notificationsGateway.getUnreadCount).mockResolvedValueOnce(150)

    render(<NotificationBell />)

    await waitFor(() => {
      const badge = screen.getByTestId('notification-badge')
      expect(badge).toBeDefined()
      expect(badge.textContent).toBe('99+')
    })
  })
})
