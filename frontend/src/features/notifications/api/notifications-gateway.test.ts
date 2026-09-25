import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { notificationsGateway, NotificationApiError } from './notifications-gateway'

describe('notificationsGateway', () => {
  const originalFetch = global.fetch

  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    global.fetch = originalFetch
  })

  it('lists notifications successfully', async () => {
    const mockResponse = {
      items: [
        {
          id: 'notif-1',
          severity: 'warning',
          title: 'Stock low',
          body: 'Item X is low',
          occurred_at: '2026-09-25T10:00:00Z',
          read_at: null,
        },
      ],
      total: 1,
      page: 1,
      per_page: 20,
      unread_count: 1,
    }

    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => mockResponse,
    })

    const result = await notificationsGateway.listNotifications({
      page: 1,
      unread_only: true,
    })
    expect(result.items).toHaveLength(1)
    expect(result.unread_count).toBe(1)
  })

  it('returns graceful fallback if notification service fails on listNotifications', async () => {
    global.fetch = vi.fn().mockRejectedValueOnce(new Error('Network error'))

    const result = await notificationsGateway.listNotifications()
    expect(result.items).toEqual([])
    expect(result.total).toBe(0)
    expect(result.unread_count).toBe(0)
  })

  it('gets unread count and falls back to 0 on failure', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({ unread_count: 7 }),
    })

    const count = await notificationsGateway.getUnreadCount()
    expect(count).toBe(7)

    global.fetch = vi.fn().mockRejectedValueOnce(new Error('Network timeout'))
    const fallbackCount = await notificationsGateway.getUnreadCount()
    expect(fallbackCount).toBe(0)
  })

  it('marks a notification as read', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({ success: true }),
    })

    const success = await notificationsGateway.markRead('notif-1')
    expect(success).toBe(true)
  })

  it('marks all notifications as read', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({ updated_count: 4 }),
    })

    const updated = await notificationsGateway.markAllRead('notif-4')
    expect(updated).toBe(4)
  })

  it('gets and updates notification preferences', async () => {
    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({ preferences: { info: true, warning: false, critical: true } }),
    })

    const prefs = await notificationsGateway.getPreferences()
    expect(prefs.warning).toBe(false)

    global.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        preferences: { info: false, warning: false, critical: true },
      }),
    })

    const updated = await notificationsGateway.updatePreferences({
      info: false,
      warning: false,
      critical: true,
    })
    expect(updated.info).toBe(false)
  })
})
