import type { components } from '../../../shared/api/generated/schema'

export type WorkspaceCapability = components['schemas']['WorkspaceCapability']

/**
 * Checks whether the given capabilities list includes a specific capability.
 * Defaults to fail-closed (false) if capabilities are missing or undefined.
 * Frontend NEVER infers capabilities from roles; capabilities are sourced directly from the backend.
 */
export function hasCapability(
  capabilities: readonly WorkspaceCapability[] | null | undefined,
  capability: WorkspaceCapability,
): boolean {
  if (!capabilities || !Array.isArray(capabilities)) {
    return false
  }

  return capabilities.includes(capability)
}

/**
 * Helper to check multiple capabilities (logical AND - must have all specified).
 */
export function hasAllCapabilities(
  capabilities: readonly WorkspaceCapability[] | null | undefined,
  required: readonly WorkspaceCapability[],
): boolean {
  if (!capabilities || !Array.isArray(capabilities)) {
    return false
  }

  return required.every((cap) => capabilities.includes(cap))
}

/**
 * Helper to check multiple capabilities (logical OR - must have at least one).
 */
export function hasAnyCapability(
  capabilities: readonly WorkspaceCapability[] | null | undefined,
  required: readonly WorkspaceCapability[],
): boolean {
  if (!capabilities || !Array.isArray(capabilities)) {
    return false
  }

  return required.some((cap) => capabilities.includes(cap))
}
