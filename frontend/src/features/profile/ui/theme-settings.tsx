'use client'

import React, { useEffect, useState } from 'react'
import { Sun, Moon, Laptop } from 'lucide-react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { cn } from '@/lib/utils'

export type ThemePreference = 'light' | 'dark' | 'system'

const THEME_STORAGE_KEY = 'autobi-theme'

function applyTheme(theme: ThemePreference) {
  if (typeof document === 'undefined') return

  const root = document.documentElement
  let effectiveTheme: 'light' | 'dark' = 'dark'

  if (theme === 'system') {
    effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light'
  } else {
    effectiveTheme = theme
  }

  root.classList.toggle('dark', effectiveTheme === 'dark')
  root.style.colorScheme = effectiveTheme
  window.localStorage.setItem(THEME_STORAGE_KEY, theme)
}

export function ThemeSettings() {
  const [selectedTheme, setSelectedTheme] = useState<ThemePreference>(() => {
    if (typeof window !== 'undefined') {
      const saved = window.localStorage.getItem(
        THEME_STORAGE_KEY,
      ) as ThemePreference | null
      if (saved === 'light' || saved === 'dark' || saved === 'system') {
        return saved
      }
    }
    return 'dark'
  })

  useEffect(() => {
    if (selectedTheme !== 'system') return

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    const handleChange = () => {
      applyTheme('system')
    }

    mediaQuery.addEventListener('change', handleChange)
    return () => mediaQuery.removeEventListener('change', handleChange)
  }, [selectedTheme])

  const handleSelectTheme = (theme: ThemePreference) => {
    setSelectedTheme(theme)
    applyTheme(theme)
  }

  const themes: {
    id: ThemePreference
    label: string
    icon: React.ComponentType<{ className?: string }>
  }[] = [
    { id: 'light', label: 'Светлая', icon: Sun },
    { id: 'dark', label: 'Тёмная', icon: Moon },
    { id: 'system', label: 'Системная', icon: Laptop },
  ]

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Оформление</CardTitle>
        <CardDescription>
          Выберите цветовую тему интерфейса. Настройка сохраняется локально на этом
          устройстве.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {themes.map(({ id, label, icon: Icon }) => {
            const isSelected = selectedTheme === id
            return (
              <button
                key={id}
                type="button"
                onClick={() => handleSelectTheme(id)}
                className={cn(
                  'flex items-center gap-3 rounded-lg border p-4 text-left transition-all cursor-pointer',
                  isSelected
                    ? 'border-emerald-500 bg-emerald-500/10 text-foreground ring-1 ring-emerald-500'
                    : 'border-border bg-card hover:bg-accent hover:text-accent-foreground text-muted-foreground',
                )}
                data-testid={`theme-option-${id}`}
              >
                <Icon
                  className={cn(
                    'h-5 w-5 shrink-0',
                    isSelected ? 'text-emerald-500' : 'text-muted-foreground',
                  )}
                />
                <span className="font-medium text-sm">{label}</span>
              </button>
            )
          })}
        </div>
      </CardContent>
    </Card>
  )
}
