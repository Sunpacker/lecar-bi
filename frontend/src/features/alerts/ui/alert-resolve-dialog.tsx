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
import { Label } from '@/components/ui/label'
import { CheckCircle2 } from 'lucide-react'

interface AlertResolveDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  alertId: string | null
  alertTitle?: string
  onConfirm: (alertId: string, note: string) => Promise<void>
  isSubmitting?: boolean
}

export function AlertResolveDialog({
  open,
  onOpenChange,
  alertId,
  alertTitle,
  onConfirm,
  isSubmitting,
}: AlertResolveDialogProps) {
  const [note, setNote] = useState('')

  const handleResolve = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!alertId) return

    await onConfirm(alertId, note)
    setNote('')
    onOpenChange(false)
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-md p-6">
        <SheetHeader className="mb-4">
          <div className="flex items-center gap-2 text-emerald-500">
            <CheckCircle2 className="h-5 w-5" />
            <SheetTitle className="text-lg font-semibold">Закрыть алерт</SheetTitle>
          </div>
          <SheetDescription className="text-xs text-muted-foreground">
            {alertTitle
              ? `Отметить алерт «${alertTitle}» как устранённый.`
              : 'Отметить выбранный алерт как решённый.'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleResolve} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="resolve-note" className="text-sm font-medium">
              Причина или комментарий к решению
            </Label>
            <textarea
              id="resolve-note"
              rows={4}
              placeholder="Например: Заказ оформлен у поставщика, ожидается поставка..."
              value={note}
              onChange={(e) => setNote(e.target.value)}
              className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
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
              disabled={isSubmitting}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isSubmitting ? 'Закрытие...' : 'Подтвердить закрытие'}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  )
}
