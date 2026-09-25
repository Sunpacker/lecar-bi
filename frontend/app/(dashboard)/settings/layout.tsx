'use client'

import React from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { User, Lock, Building, Users, Bell } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useWorkspaceAccess } from '@/src/features/workspace/ui/workspace-access-provider'

interface TabItem {
  name: string
  href: string
  icon: React.ComponentType<{ className?: string }>
}

const SETTINGS_TABS: TabItem[] = [
  { name: 'Профиль', href: '/settings', icon: User },
  { name: 'Безопасность', href: '/settings/security', icon: Lock },
  { name: 'Рабочее пространство', href: '/settings/workspace', icon: Building },
  { name: 'Доступ и участники', href: '/settings/access', icon: Users },
  { name: 'Уведомления', href: '/settings/notifications', icon: Bell },
]

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const { hasCapability } = useWorkspaceAccess()

  const tabs = SETTINGS_TABS.filter((tab) => {
    if (tab.href === '/settings/access') {
      return hasCapability('workspace.members.manage')
    }
    return true
  })

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
          AUTOBI / НАСТРОЙКИ
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Настройки
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Управление профилем, безопасностью, параметрами пространства, участниками и
          уведомлениями.
        </p>
      </div>

      {/* Tabs Navigation */}
      <div className="mb-8 border-b border-border">
        <nav
          className="flex space-x-2 sm:space-x-4 overflow-x-auto pb-px"
          aria-label="Вкладки настроек"
        >
          {tabs.map((tab) => {
            const isActive = pathname === tab.href
            const Icon = tab.icon

            return (
              <Link
                key={tab.href}
                href={tab.href}
                className={cn(
                  'flex items-center gap-2 px-3 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                  isActive
                    ? 'border-emerald-500 text-emerald-500 font-semibold'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border',
                )}
              >
                <Icon className="h-4 w-4 shrink-0" />
                <span>{tab.name}</span>
              </Link>
            )
          })}
        </nav>
      </div>

      <div className="max-w-4xl">{children}</div>
    </main>
  )
}
