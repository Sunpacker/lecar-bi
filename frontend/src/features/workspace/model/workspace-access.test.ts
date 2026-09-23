import { describe, expect, it } from 'vitest'
import {
  hasAllCapabilities,
  hasAnyCapability,
  hasCapability,
  type WorkspaceCapability,
} from './workspace-access'

describe('workspace-access model', () => {
  it('returns true when capability is present', () => {
    const caps: WorkspaceCapability[] = [
      'analytics.view',
      'dashboards.view',
      'dashboards.manage',
    ]
    expect(hasCapability(caps, 'dashboards.manage')).toBe(true)
    expect(hasCapability(caps, 'analytics.view')).toBe(true)
  })

  it('returns false when capability is absent', () => {
    const caps: WorkspaceCapability[] = ['analytics.view', 'dashboards.view']
    expect(hasCapability(caps, 'dashboards.manage')).toBe(false)
    expect(hasCapability(caps, 'workspace.members.manage')).toBe(false)
  })

  it('fails closed when capabilities list is undefined or null', () => {
    expect(hasCapability(undefined, 'analytics.view')).toBe(false)
    expect(hasCapability(null, 'analytics.view')).toBe(false)
    expect(hasCapability([] as WorkspaceCapability[], 'analytics.view')).toBe(false)
  })

  it('evaluates hasAllCapabilities correctly', () => {
    const caps: WorkspaceCapability[] = [
      'analytics.view',
      'dashboards.view',
      'dashboards.manage',
    ]
    expect(hasAllCapabilities(caps, ['analytics.view', 'dashboards.manage'])).toBe(true)
    expect(hasAllCapabilities(caps, ['analytics.view', 'workspace.members.manage'])).toBe(
      false,
    )
    expect(hasAllCapabilities(undefined, ['analytics.view'])).toBe(false)
  })

  it('evaluates hasAnyCapability correctly', () => {
    const caps: WorkspaceCapability[] = ['analytics.view']
    expect(hasAnyCapability(caps, ['analytics.view', 'dashboards.manage'])).toBe(true)
    expect(hasAnyCapability(caps, ['dashboards.view', 'dashboards.manage'])).toBe(false)
    expect(hasAnyCapability(null, ['analytics.view'])).toBe(false)
  })
})
