import type { Preview } from '@storybook/nextjs-vite'
import { useEffect, type ReactNode } from 'react'
import '../app/globals.css'
import './fonts.css'

function ThemeRoot({ children, theme }: { children: ReactNode; theme: string }) {
  useEffect(() => {
    const dark = theme === 'dark'
    document.documentElement.classList.toggle('dark', dark)
    document.body.classList.toggle('dark', dark)
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
    return () => {
      document.documentElement.classList.remove('dark')
      document.body.classList.remove('dark')
      document.documentElement.style.colorScheme = ''
    }
  }, [theme])

  return (
    <div
      className={
        theme === 'dark'
          ? 'dark min-h-screen bg-background p-4 text-foreground'
          : 'min-h-screen bg-background p-4 text-foreground'
      }
      style={{ fontFamily: 'Geist, sans-serif' }}
    >
      {children}
    </div>
  )
}

const preview: Preview = {
  globalTypes: {
    theme: {
      description: 'Тема интерфейса',
      toolbar: {
        icon: 'circlehollow',
        items: [
          { value: 'light', title: 'Светлая' },
          { value: 'dark', title: 'Тёмная' },
        ],
      },
    },
  },
  initialGlobals: { theme: 'light' },
  decorators: [
    (Story, context) => (
      <ThemeRoot theme={String(context.globals.theme)}>
        <Story />
      </ThemeRoot>
    ),
  ],
  parameters: {
    layout: 'fullscreen',
    controls: { expanded: true },
    a11y: { test: 'error' },
  },
  tags: ['autodocs'],
}

export default preview
