import { cookies } from 'next/headers'

export const SELECTED_WORKSPACE_COOKIE = 'autobi_workspace'

export async function getSelectedWorkspaceId(): Promise<string | undefined> {
  const cookieStore = await cookies()
  return cookieStore.get(SELECTED_WORKSPACE_COOKIE)?.value
}
