import { fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { Header } from './header'

describe('Header theme toggle', () => {
  afterEach(() => {
    document.documentElement.classList.remove('dark')
    document.documentElement.style.colorScheme = ''
    window.localStorage.clear()
  })

  it('switches between light and dark themes and saves the preference', () => {
    document.documentElement.classList.add('dark')
    render(<Header workspaceContext={null} accessibleWorkspaces={[]} />)

    const toggle = screen.getByRole('button', {
      name: 'Переключить цветовую тему',
    })

    fireEvent.click(toggle)
    expect(document.documentElement).not.toHaveClass('dark')
    expect(document.documentElement.style.colorScheme).toBe('light')
    expect(window.localStorage.getItem('autobi-theme')).toBe('light')

    fireEvent.click(toggle)
    expect(document.documentElement).toHaveClass('dark')
    expect(document.documentElement.style.colorScheme).toBe('dark')
    expect(window.localStorage.getItem('autobi-theme')).toBe('dark')
  })
})
