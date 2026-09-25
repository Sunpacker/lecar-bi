'use client'

import { useEffect, useState, useCallback, useRef } from 'react'
import { notificationsGateway } from '../api/notifications-gateway'

export function useNotificationUnreadCount(initialCount: number = 0) {
  const [unreadCount, setUnreadCount] = useState<number>(initialCount)
  const isFetchingRef = useRef(false)

  const refresh = useCallback(async () => {
    if (isFetchingRef.current) return
    isFetchingRef.current = true
    try {
      const count = await notificationsGateway.getUnreadCount()
      setUnreadCount(count)
    } finally {
      isFetchingRef.current = false
    }
  }, [])

  useEffect(() => {
    void refresh()

    const interval = setInterval(() => {
      if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
        void refresh()
      }
    }, 30000)

    const onFocus = () => {
      void refresh()
    }

    const onVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        void refresh()
      }
    }

    window.addEventListener('focus', onFocus)
    document.addEventListener('visibilitychange', onVisibilityChange)

    return () => {
      clearInterval(interval)
      window.removeEventListener('focus', onFocus)
      document.removeEventListener('visibilitychange', onVisibilityChange)
    }
  }, [refresh])

  return { unreadCount, setUnreadCount, refresh }
}
