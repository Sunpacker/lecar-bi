'use client'

import React, { useState } from 'react'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { UserPlus, Loader2, AlertCircle } from 'lucide-react'
import { workspaceGateway, type Invitation } from '../api/workspace-gateway'

interface InviteMemberDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  workspaceId: string
  onInvitationCreated: (invitation: Invitation) => void
}

export function InviteMemberDialog({
  open,
  onOpenChange,
  workspaceId,
  onInvitationCreated,
}: InviteMemberDialogProps) {
  const [email, setEmail] = useState('')
  const [role, setRole] = useState<'member' | 'viewer'>('member')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMessage(null)

    const trimmed = email.trim().toLowerCase()
    if (!trimmed || !trimmed.includes('@')) {
      setErrorMessage('Укажите корректный адрес электронной почты')
      return
    }

    setIsSubmitting(true)
    try {
      const invitation = await workspaceGateway.createInvitation(
        workspaceId,
        trimmed,
        role,
      )
      onInvitationCreated(invitation)
      setEmail('')
      setRole('member')
      onOpenChange(false)
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Не удалось отправить приглашение')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-md p-6">
        <SheetHeader className="mb-4">
          <div className="flex items-center gap-2 text-emerald-500">
            <UserPlus className="h-5 w-5" />
            <SheetTitle className="text-lg font-semibold">
              Пригласить участника
            </SheetTitle>
          </div>
          <SheetDescription className="text-xs text-muted-foreground">
            Отправьте приглашение по электронной почте. Ссылка действительна 7 дней.
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {errorMessage && (
            <div
              role="alert"
              className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
            >
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{errorMessage}</span>
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="invite-email">Email пользователя</Label>
            <Input
              id="invite-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              disabled={isSubmitting}
              required
              placeholder="colleague@company.com"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="invite-role">Роль в пространстве</Label>
            <select
              id="invite-role"
              value={role}
              onChange={(e) => setRole(e.target.value as 'member' | 'viewer')}
              disabled={isSubmitting}
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <option value="member">
                Участник (полный доступ к данным и аналитике)
              </option>
              <option value="viewer">
                Наблюдатель (только чтение отчётов и панелей)
              </option>
            </select>
          </div>

          <div className="flex justify-end gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isSubmitting}
            >
              Отмена
            </Button>
            <Button
              type="submit"
              disabled={isSubmitting || !email.trim()}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Отправка...
                </>
              ) : (
                'Отправить приглашение'
              )}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  )
}
