import React from 'react'
import { Badge } from '@/components/ui/badge'
import { CheckCircle2, AlertTriangle, XCircle, Clock, Loader2 } from 'lucide-react'
import type { ImportStatus } from '../api/import-gateway'

interface ImportStatusBadgeProps {
  status: ImportStatus
}

export function ImportStatusBadge({ status }: ImportStatusBadgeProps) {
  switch (status) {
    case 'pending':
      return (
        <Badge
          variant="outline"
          className="border-amber-500/40 text-amber-500 bg-amber-500/10 gap-1.5 font-medium"
        >
          <Clock className="h-3 w-3" />В очереди
        </Badge>
      )
    case 'validating':
      return (
        <Badge
          variant="outline"
          className="border-sky-500/40 text-sky-500 bg-sky-500/10 gap-1.5 font-medium animate-pulse"
        >
          <Loader2 className="h-3 w-3 animate-spin" />
          Валидация
        </Badge>
      )
    case 'processing':
      return (
        <Badge
          variant="outline"
          className="border-indigo-500/40 text-indigo-400 bg-indigo-500/10 gap-1.5 font-medium animate-pulse"
        >
          <Loader2 className="h-3 w-3 animate-spin" />
          Обработка
        </Badge>
      )
    case 'completed':
      return (
        <Badge
          variant="outline"
          className="border-emerald-500/40 text-emerald-500 bg-emerald-500/10 gap-1.5 font-medium"
        >
          <CheckCircle2 className="h-3 w-3" />
          Завершено
        </Badge>
      )
    case 'completed_with_errors':
      return (
        <Badge
          variant="outline"
          className="border-amber-500/40 text-amber-500 bg-amber-500/10 gap-1.5 font-medium"
        >
          <AlertTriangle className="h-3 w-3" />
          Есть ошибки
        </Badge>
      )
    case 'failed':
      return (
        <Badge
          variant="outline"
          className="border-rose-500/40 text-rose-500 bg-rose-500/10 gap-1.5 font-medium"
        >
          <XCircle className="h-3 w-3" />
          Сбой
        </Badge>
      )
  }
}
