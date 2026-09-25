'use client'

import React, { useEffect, useState } from 'react'
import { RotateCw, XCircle, Loader2, CheckCircle2, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { workspaceGateway, type Invitation } from '../api/workspace-gateway'
import { ROLE_LABELS } from './workspace-member-list'

interface WorkspaceInvitationsListProps {
  workspaceId: string
  canManage: boolean
  lastAddedInvitation?: Invitation | null
}

export function WorkspaceInvitationsList({
  workspaceId,
  canManage,
  lastAddedInvitation,
}: WorkspaceInvitationsListProps) {
  const [invitations, setInvitations] = useState<Invitation[]>([])
  const [loading, setLoading] = useState(true)
  const [actionLoadingId, setActionLoadingId] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const loadInvitations = React.useCallback(async () => {
    if (!canManage) {
      setLoading(false)
      return
    }

    try {
      setLoading(true)
      const data = await workspaceGateway.listInvitations(workspaceId)
      setInvitations(data.filter((inv) => inv.status === 'pending'))
    } catch {
      // Non-blocking error
    } finally {
      setLoading(false)
    }
  }, [workspaceId, canManage])

  useEffect(() => {
    let isMounted = true
    void Promise.resolve().then(() => {
      if (isMounted) {
        void loadInvitations()
      }
    })
    return () => {
      isMounted = false
    }
  }, [loadInvitations])

  useEffect(() => {
    if (lastAddedInvitation && lastAddedInvitation.status === 'pending') {
      void Promise.resolve().then(() => {
        setInvitations((prev) => [
          lastAddedInvitation,
          ...prev.filter((i) => i.id !== lastAddedInvitation.id),
        ])
        setSuccessMessage(`Приглашение для ${lastAddedInvitation.email} отправлено.`)
      })
    }
  }, [lastAddedInvitation])

  const handleResend = async (invitationId: string, email: string) => {
    setActionLoadingId(invitationId)
    setErrorMessage(null)
    setSuccessMessage(null)

    try {
      const updated = await workspaceGateway.resendInvitation(workspaceId, invitationId)
      setInvitations((prev) =>
        prev.map((inv) => (inv.id === invitationId ? updated : inv)),
      )
      setSuccessMessage(
        `Приглашение для ${email} повторно отправлено. Срок действия обновлён.`,
      )
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Не удалось повторно отправить приглашение')
      }
    } finally {
      setActionLoadingId(null)
    }
  }

  const handleCancel = async (invitationId: string, email: string) => {
    setActionLoadingId(invitationId)
    setErrorMessage(null)
    setSuccessMessage(null)

    try {
      await workspaceGateway.cancelInvitation(workspaceId, invitationId)
      setInvitations((prev) => prev.filter((inv) => inv.id !== invitationId))
      setSuccessMessage(`Приглашение для ${email} отменено.`)
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Не удалось отменить приглашение')
      }
    } finally {
      setActionLoadingId(null)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center p-6 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin mr-2" />
        <span className="text-sm">Загрузка приглашений...</span>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {successMessage && (
        <div
          role="status"
          className="flex items-center gap-2 rounded-lg border border-emerald-500/50 bg-emerald-500/10 p-3 text-sm text-emerald-600 dark:text-emerald-400"
        >
          <CheckCircle2 className="h-4 w-4 shrink-0" />
          <span>{successMessage}</span>
        </div>
      )}

      {errorMessage && (
        <div
          role="alert"
          className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
        >
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{errorMessage}</span>
        </div>
      )}

      {invitations.length === 0 ? (
        <div className="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
          Нет активных ожидающих приглашений.
        </div>
      ) : (
        <div className="rounded-lg border border-border bg-card overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm" data-testid="invitations-table">
              <thead className="border-b border-border bg-muted/40 text-muted-foreground">
                <tr>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Email
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Роль
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Действительно до
                  </th>
                  {canManage && (
                    <th scope="col" className="px-4 py-3 font-medium text-right">
                      Действия
                    </th>
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {invitations.map((inv) => {
                  const isOperating = actionLoadingId === inv.id
                  const expiresFormatted = new Date(inv.expires_at).toLocaleDateString(
                    'ru-RU',
                    {
                      day: 'numeric',
                      month: 'short',
                      year: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    },
                  )

                  return (
                    <tr key={inv.id} className="hover:bg-muted/20">
                      <td className="px-4 py-3 font-medium text-foreground">
                        {inv.email}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {ROLE_LABELS[inv.role] || inv.role}
                      </td>
                      <td className="px-4 py-3 text-xs text-muted-foreground">
                        {expiresFormatted}
                      </td>
                      {canManage && (
                        <td className="px-4 py-3 text-right">
                          <div className="flex items-center justify-end gap-2">
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={isOperating}
                              onClick={() => handleResend(inv.id, inv.email)}
                              className="h-8 px-2 text-xs text-muted-foreground hover:text-foreground gap-1"
                              title="Отправить ссылку повторно"
                            >
                              <RotateCw
                                className={`h-3.5 w-3.5 ${isOperating ? 'animate-spin' : ''}`}
                              />
                              <span className="hidden sm:inline">Повторить</span>
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={isOperating}
                              onClick={() => handleCancel(inv.id, inv.email)}
                              className="h-8 px-2 text-xs text-destructive hover:bg-destructive/10 gap-1"
                              title="Отозвать приглашение"
                            >
                              <XCircle className="h-3.5 w-3.5" />
                              <span className="hidden sm:inline">Отменить</span>
                            </Button>
                          </div>
                        </td>
                      )}
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}
