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

function getBaseUrl(): string {
  if (typeof window === 'undefined') {
    return process.env.NOTIFICATION_INTERNAL_URL || 'http://localhost:8081/api/v1'
  }
  return '/api/notifications'
}

async function getAuthHeaders(): Promise<Headers> {
  const headers = new Headers()
  headers.set('Accept', 'application/json')
  headers.set('Content-Type', 'application/json')

  if (typeof window === 'undefined') {
    // Server-side direct inter-service call
    const secret =
      process.env.NOTIFICATION_SHARED_SECRET || 'test-notification-secret-key-12345'
    headers.set('X-Server-Secret', secret)

    try {
      const { getSession, getWorkspaceCookie } = await import('../../auth/model/session')
      const session = await getSession()
      if (session) {
        headers.set('X-User-Id', session.userId)
      }
      const workspaceId = await getWorkspaceCookie()
      if (workspaceId) {
        headers.set('X-Workspace-Id', workspaceId)
      }
    } catch {
      // outside request context
    }
  }

  return headers
}

export const notificationsGateway = {
  async listNotifications(params?: {
    page?: number
    per_page?: number
    unread_only?: boolean
    severity?: NotificationSeverity
  }): Promise<NotificationList> {
    const url = new URL(`${getBaseUrl()}/notifications`, 'http://localhost')
    if (params?.page) url.searchParams.set('page', String(params.page))
    if (params?.per_page) url.searchParams.set('per_page', String(params.per_page))
    if (params?.unread_only !== undefined)
      url.searchParams.set('unread_only', String(params.unread_only))
    if (params?.severity) url.searchParams.set('severity', params.severity)

    const requestUrl =
      typeof window === 'undefined'
        ? url.toString()
        : `/api/notifications/notifications${url.search}`

    const headers = await getAuthHeaders()

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
    const requestUrl =
      typeof window === 'undefined'
        ? `${getBaseUrl()}/notifications/unread-count`
        : '/api/notifications/notifications/unread-count'

    const headers = await getAuthHeaders()

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
    const requestUrl =
      typeof window === 'undefined'
        ? `${getBaseUrl()}/notifications/${notificationId}/read`
        : `/api/notifications/notifications/${notificationId}/read`

    const headers = await getAuthHeaders()

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
    const requestUrl =
      typeof window === 'undefined'
        ? `${getBaseUrl()}/notifications/read-all`
        : '/api/notifications/notifications/read-all'

    const headers = await getAuthHeaders()

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
    const requestUrl =
      typeof window === 'undefined'
        ? `${getBaseUrl()}/notification-preferences`
        : '/api/notifications/notification-preferences'

    const headers = await getAuthHeaders()

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
    const requestUrl =
      typeof window === 'undefined'
        ? `${getBaseUrl()}/notification-preferences`
        : '/api/notifications/notification-preferences'

    const headers = await getAuthHeaders()

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
