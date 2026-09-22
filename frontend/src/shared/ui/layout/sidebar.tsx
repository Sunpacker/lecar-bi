'use client'

import React from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import { BarChart3, Package, Users, Bell, Settings, LayoutDashboard } from 'lucide-react'

export interface NavigationItem {
  name: string
  href: string
  icon: React.ComponentType<{ className?: string }>
  disabled?: boolean
  badge?: string
}

export const NAVIGATION_ITEMS: NavigationItem[] = [
  { name: 'Sales Analytics', href: '/', icon: BarChart3 },
  { name: 'Inventory', href: '/inventory', icon: Package },
  { name: 'Suppliers', href: '/suppliers', icon: Users, disabled: true, badge: 'Скоро' },
  { name: 'Alerts', href: '/alerts', icon: Bell, disabled: true, badge: 'Скоро' },
  { name: 'Settings', href: '/settings', icon: Settings, disabled: true, badge: 'Скоро' },
]

export function NavigationList({ onItemClick }: { onItemClick?: () => void }) {
  const pathname = usePathname()

  return (
    <div className="space-y-1">
      {NAVIGATION_ITEMS.map((item) => {
        if (item.disabled) {
          return (
            <div
              key={item.name}
              aria-disabled="true"
              className="flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium text-muted-foreground/45 cursor-not-allowed select-none transition-colors"
              title="Раздел в разработке"
            >
              <div className="flex items-center gap-x-3">
                <item.icon className="h-5 w-5 shrink-0 text-muted-foreground/35" />
                <span>{item.name}</span>
              </div>
              {item.badge && (
                <span className="text-[10px] font-normal px-1.5 py-0.5 rounded-full bg-muted/60 text-muted-foreground/70 border border-border/40">
                  {item.badge}
                </span>
              )}
            </div>
          )
        }

        const isActive = pathname === item.href
        return (
          <Link
            key={item.href}
            href={item.href}
            onClick={onItemClick}
            className={cn(
              'flex items-center gap-x-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
              isActive
                ? 'bg-emerald-500/10 text-emerald-500 font-semibold'
                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            )}
          >
            <item.icon className="h-5 w-5 shrink-0" />
            <span>{item.name}</span>
          </Link>
        )
      })}
    </div>
  )
}

export function Sidebar() {
  return (
    <aside className="hidden lg:flex w-64 flex-col border-r border-border bg-background shrink-0">
      <div className="flex h-16 shrink-0 items-center gap-x-2 px-6 border-b border-border">
        <LayoutDashboard className="h-6 w-6 text-emerald-500" />
        <span className="font-bold text-lg tracking-tight">AutoBI</span>
      </div>
      <nav className="flex-1 px-3 py-4 overflow-y-auto">
        <NavigationList />
      </nav>
    </aside>
  )
}
