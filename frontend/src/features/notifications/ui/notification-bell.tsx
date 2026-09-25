'use client'

import React from 'react'
import Link from 'next/link'
import { Bell } from 'lucide-react'
import { useNotificationUnreadCount } from '../model/use-notifications'
import { cn } from '@/lib/utils'

export function NotificationBell({ className }: { className?: string }) {
  const { unreadCount } = useNotificationUnreadCount()

  const label =
    unreadCount > 0 ? `Уведомления: ${unreadCount} непрочитанных` : 'Уведомления'

  return (
    <Link
      href="/notifications"
      aria-label={label}
      title={label}
      data-testid="notification-bell"
      className={cn(
        'relative inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
        className,
      )}
    >
      <Bell className="h-5 w-5" />
      {unreadCount > 0 && (
        <span
          data-testid="notification-badge"
          className="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white shadow-xs animate-in zoom-in-50"
        >
          {unreadCount > 99 ? '99+' : unreadCount}
        </span>
      )}
    </Link>
  )
}
