import { render, screen, fireEvent } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { WorkspaceSwitcher } from './workspace-switcher'

describe('WorkspaceSwitcher', () => {
  const workspaces = [
    {
      id: 'ws-1',
      name: 'AutoParts Retail',
      slug: 'autoparts-retail',
      role: 'owner' as const,
      capabilities: ['analytics.view' as const],
    },
    {
      id: 'ws-2',
      name: 'Fleet Direct',
      slug: 'fleet-direct',
      role: 'member' as const,
      capabilities: ['analytics.view' as const],
    },
  ]

  it('renders available workspaces and triggers callback on change', () => {
    const handleSelect = vi.fn()
    render(
      <WorkspaceSwitcher
        currentWorkspaceId="ws-1"
        workspaces={workspaces}
        onSelectWorkspace={handleSelect}
      />,
    )

    const select = screen.getByLabelText(/current workspace/i) as HTMLSelectElement
    expect(select.value).toBe('ws-1')

    fireEvent.change(select, { target: { value: 'ws-2' } })
    expect(handleSelect).toHaveBeenCalledWith('ws-2')
  })
})
