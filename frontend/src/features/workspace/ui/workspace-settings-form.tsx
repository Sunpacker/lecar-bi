'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Loader2, CheckCircle2, AlertCircle } from 'lucide-react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { workspaceGateway, type Workspace } from '../api/workspace-gateway'

interface WorkspaceSettingsFormProps {
  workspace: Workspace
  canManage: boolean
}

export function WorkspaceSettingsForm({
  workspace,
  canManage,
}: WorkspaceSettingsFormProps) {
  const router = useRouter()
  const [name, setName] = useState(workspace.name)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!canManage) return

    setErrorMessage(null)
    setSuccessMessage(null)

    const trimmed = name.trim()
    if (!trimmed) {
      setErrorMessage('Название пространства не может быть пустым')
      return
    }

    setIsSubmitting(true)
    try {
      const updated = await workspaceGateway.renameWorkspace(workspace.id, trimmed)
      setName(updated.name)
      setSuccessMessage('Название рабочего пространства успешно изменено')
      router.refresh()
    } catch (err) {
      if (err instanceof Error) {
        setErrorMessage(err.message)
      } else {
        setErrorMessage('Произошла ошибка при переименовании пространства')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Параметры пространства</CardTitle>
        <CardDescription>
          Управление названием и идентификатором текущего рабочего пространства.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
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

          <div className="space-y-2">
            <Label htmlFor="workspace-slug">Идентификатор (Slug)</Label>
            <Input
              id="workspace-slug"
              type="text"
              value={workspace.slug}
              disabled
              readOnly
              className="bg-muted text-muted-foreground cursor-not-allowed font-mono text-xs"
            />
            <p className="text-xs text-muted-foreground">
              Slug формируется автоматически и используется в системных идентификаторах.
            </p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="workspace-name">Название пространства</Label>
            <Input
              id="workspace-name"
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              disabled={isSubmitting || !canManage}
              required
              minLength={1}
              maxLength={255}
              placeholder="Например: Главный офис"
            />
            {!canManage && (
              <p className="text-xs text-muted-foreground">
                Только владелец рабочего пространства может изменять его название.
              </p>
            )}
          </div>

          {canManage && (
            <div className="pt-2">
              <Button
                type="submit"
                disabled={isSubmitting || name.trim() === workspace.name}
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                {isSubmitting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Сохранение...
                  </>
                ) : (
                  'Сохранить изменения'
                )}
              </Button>
            </div>
          )}
        </form>
      </CardContent>
    </Card>
  )
}
