import type { components } from '../../../shared/api/generated/notification-schema'

export type NotificationItem = components['schemas']['NotificationResponse']
export type NotificationList = components['schemas']['NotificationListResponse']
export type NotificationPreferences = components['schemas']['NotificationPreferences']
export type NotificationSeverity =
  components['schemas']['NotificationResponse']['severity']

export class NotificationApiError extends Error {
  constructor(
    message: string,
    public readonly code?: string,
    public readonly status?: number,
  ) {
    super(message)
    this.name = 'NotificationApiError'
  }
}

function getAuthHeaders(): Headers {
  const headers = new Headers()
  headers.set('Accept', 'application/json')
  headers.set('Content-Type', 'application/json')
  return headers
}

export const notificationsGateway = {
  async listNotifications(params?: {
    page?: number
    per_page?: number
    unread_only?: boolean
    severity?: NotificationSeverity
  }): Promise<NotificationList> {
    const searchParams = new URLSearchParams()
    if (params?.page) searchParams.set('page', String(params.page))
    if (params?.per_page) searchParams.set('per_page', String(params.per_page))
    if (params?.unread_only !== undefined)
      searchParams.set('unread_only', String(params.unread_only))
    if (params?.severity) searchParams.set('severity', params.severity)

    const query = searchParams.toString()
    const requestUrl = `/api/notifications/notifications${query ? `?${query}` : ''}`
    const headers = getAuthHeaders()

    try {
      const res = await fetch(requestUrl, {
        method: 'GET',
        headers,
      })

      if (!res.ok) {
        const errorData = await res.json().catch(() => ({}))
        throw new NotificationApiError(
          errorData.message || 'Ошибка загрузки уведомлений',
          errorData.code,
          res.status,
        )
      }

      return await res.json()
    } catch (err) {
      if (err instanceof NotificationApiError) throw err
      return {
        items: [],
        total: 0,
        page: params?.page || 1,
        per_page: params?.per_page || 20,
        unread_count: 0,
      }
    }
  },

  async getUnreadCount(): Promise<number> {
    const requestUrl = '/api/notifications/notifications/unread-count'
    const headers = getAuthHeaders()

    try {
      const res = await fetch(requestUrl, {
        method: 'GET',
        headers,
      })

      if (!res.ok) {
        return 0
      }

      const data = await res.json()
      return data.unread_count ?? 0
    } catch {
      return 0
    }
  },

  async markRead(notificationId: string): Promise<boolean> {
    const requestUrl = `/api/notifications/notifications/${notificationId}/read`
    const headers = getAuthHeaders()

    const res = await fetch(requestUrl, {
      method: 'POST',
      headers,
    })

    if (!res.ok) {
      const errorData = await res.json().catch(() => ({}))
      throw new NotificationApiError(
        errorData.message || 'Не удалось отметить уведомление как прочитанное',
        errorData.code,
        res.status,
      )
    }

    const data = await res.json()
    return !!data.success
  },

  async markAllRead(upToNotificationId?: string): Promise<number> {
    const requestUrl = '/api/notifications/notifications/read-all'
    const headers = getAuthHeaders()

    const res = await fetch(requestUrl, {
      method: 'POST',
      headers,
      body: JSON.stringify(
        upToNotificationId ? { up_to_notification_id: upToNotificationId } : {},
      ),
    })

    if (!res.ok) {
      const errorData = await res.json().catch(() => ({}))
      throw new NotificationApiError(
        errorData.message || 'Не удалось отметить все уведомления как прочитанные',
        errorData.code,
        res.status,
      )
    }

    const data = await res.json()
    return data.updated_count ?? 0
  },

  async getPreferences(): Promise<NotificationPreferences> {
    const requestUrl = '/api/notifications/notification-preferences'
    const headers = getAuthHeaders()

    try {
      const res = await fetch(requestUrl, {
        method: 'GET',
        headers,
      })

      if (!res.ok) {
        return { info: true, warning: true, critical: true }
      }

      const data = await res.json()
      return data.preferences ?? { info: true, warning: true, critical: true }
    } catch {
      return { info: true, warning: true, critical: true }
    }
  },

  async updatePreferences(
    preferences: NotificationPreferences,
  ): Promise<NotificationPreferences> {
    const requestUrl = '/api/notifications/notification-preferences'
    const headers = getAuthHeaders()

    const res = await fetch(requestUrl, {
      method: 'PUT',
      headers,
      body: JSON.stringify({ preferences }),
    })

    if (!res.ok) {
      const errorData = await res.json().catch(() => ({}))
      throw new NotificationApiError(
        errorData.message || 'Не удалось сохранить настройки уведомлений',
        errorData.code,
        res.status,
      )
    }

    const data = await res.json()
    return data.preferences
  },
}
