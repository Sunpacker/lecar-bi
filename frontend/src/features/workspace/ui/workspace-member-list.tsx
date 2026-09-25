'use client'

import React, { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import {
  workspaceGateway,
  WorkspaceApiError,
  type WorkspaceMember,
  type WorkspaceRole,
} from '../api/workspace-gateway'

export const ROLE_LABELS: Record<WorkspaceRole, string> = {
  owner: 'Владелец',
  member: 'Участник',
  viewer: 'Наблюдатель',
}

export const ROLE_OPTIONS: { value: WorkspaceRole; label: string }[] = [
  { value: 'owner', label: 'Владелец' },
  { value: 'member', label: 'Участник' },
  { value: 'viewer', label: 'Наблюдатель' },
]

export interface WorkspaceMemberListProps {
  workspaceId: string
  currentUserId: string
  initialMembers?: WorkspaceMember[]
}

export function WorkspaceMemberList({
  workspaceId,
  currentUserId,
  initialMembers,
}: WorkspaceMemberListProps): React.JSX.Element {
  const router = useRouter()
  const [members, setMembers] = useState<WorkspaceMember[]>(initialMembers ?? [])
  const [loading, setLoading] = useState<boolean>(!initialMembers)
  const [error, setError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [updatingUserId, setUpdatingUserId] = useState<string | null>(null)

  useEffect(() => {
    if (initialMembers) {
      return
    }

    let isMounted = true

    void Promise.resolve().then(() => {
      if (!isMounted) return
      setLoading(true)
      setError(null)

      workspaceGateway
        .listWorkspaceMembers(workspaceId)
        .then((data) => {
          if (isMounted) {
            setMembers(data)
            setLoading(false)
          }
        })
        .catch((err: unknown) => {
          if (isMounted) {
            if (
              err instanceof WorkspaceApiError &&
              err.code === 'INSUFFICIENT_CAPABILITY'
            ) {
              setError('У вас нет прав для просмотра списка участников воркспейса.')
            } else if (err instanceof Error) {
              setError(err.message)
            } else {
              setError('Не удалось загрузить список участников.')
            }
            setLoading(false)
          }
        })
    })

    return () => {
      isMounted = false
    }
  }, [currentUserId, workspaceId, initialMembers])

  const handleRoleChange = async (memberUserId: string, newRole: WorkspaceRole) => {
    setError(null)
    setSuccessMessage(null)
    setUpdatingUserId(memberUserId)

    try {
      const updatedMember = await workspaceGateway.changeMemberRole(
        workspaceId,
        memberUserId,
        newRole,
      )

      setMembers((prev) =>
        prev.map((m) => (m.user.id === memberUserId ? updatedMember : m)),
      )
      setSuccessMessage(
        `Роль пользователя «${updatedMember.user.name}» успешно изменена на «${ROLE_LABELS[newRole]}».`,
      )

      if (memberUserId === currentUserId) {
        router.refresh()
      }
    } catch (err: unknown) {
      if (err instanceof WorkspaceApiError && err.code === 'LAST_WORKSPACE_OWNER') {
        setError('Нельзя понизить единственного владельца воркспейса.')
      } else if (
        err instanceof WorkspaceApiError &&
        err.code === 'INSUFFICIENT_CAPABILITY'
      ) {
        setError('Недостаточно прав для изменения ролей участников.')
      } else if (err instanceof Error) {
        setError(err.message)
      } else {
        setError('Произошла ошибка при изменении роли.')
      }
    } finally {
      setUpdatingUserId(null)
    }
  }

  if (loading) {
    return (
      <div
        className="flex items-center justify-center p-8 text-muted-foreground"
        data-testid="members-loading"
      >
        <span>Загрузка участников...</span>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {error && (
        <div
          role="alert"
          className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive"
        >
          {error}
        </div>
      )}

      {successMessage && (
        <div
          role="status"
          className="rounded-lg border border-emerald-500/50 bg-emerald-500/10 p-4 text-sm text-emerald-600 dark:text-emerald-400"
        >
          {successMessage}
        </div>
      )}

      <div className="rounded-lg border border-border bg-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm" data-testid="members-table">
            <thead className="border-b border-border bg-muted/40 text-muted-foreground">
              <tr>
                <th scope="col" className="px-4 py-3 font-medium">
                  Пользователь
                </th>
                <th scope="col" className="px-4 py-3 font-medium">
                  Email
                </th>
                <th scope="col" className="px-4 py-3 font-medium">
                  Роль
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {members.map((member) => {
                const isSelf = member.user.id === currentUserId
                const isUpdating = updatingUserId === member.user.id

                return (
                  <tr key={member.user.id} className="hover:bg-muted/20">
                    <td className="px-4 py-3 font-medium text-foreground">
                      <div className="flex items-center gap-2">
                        <span>{member.user.name}</span>
                        {isSelf && (
                          <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-normal text-muted-foreground">
                            Вы
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">
                      {member.user.email}
                    </td>
                    <td className="px-4 py-3">
                      <label
                        htmlFor={`role-select-${member.user.id}`}
                        className="sr-only"
                      >
                        {`Роль пользователя ${member.user.name}`}
                      </label>
                      <select
                        id={`role-select-${member.user.id}`}
                        aria-label={`Роль пользователя ${member.user.name}`}
                        value={member.role}
                        disabled={isUpdating}
                        onChange={(e) =>
                          handleRoleChange(
                            member.user.id,
                            e.target.value as WorkspaceRole,
                          )
                        }
                        className="rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                      >
                        {ROLE_OPTIONS.map((opt) => (
                          <option key={opt.value} value={opt.value}>
                            {opt.label}
                          </option>
                        ))}
                      </select>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
