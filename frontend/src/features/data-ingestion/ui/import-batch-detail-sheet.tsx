'use client'

import React, { useEffect, useState, useCallback } from 'react'
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/components/ui/sheet'
import {
  Table,
  TableHeader,
  TableBody,
  TableHead,
  TableRow,
  TableCell,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  Layers,
  Clock,
  AlertCircle,
  Loader2,
} from 'lucide-react'
import { ImportStatusBadge } from './import-status-badge'
import { importGateway } from '../api/import-gateway'
import type { ImportBatchSummary, ImportFailureItem } from '../api/import-gateway'

interface ImportBatchDetailSheetProps {
  batch: ImportBatchSummary | null
  isOpen: boolean
  onClose: () => void
  userId: string
  workspaceId: string
  onRetryBatch: (batchId: string) => Promise<void>
  isRetrying: boolean
}

export function ImportBatchDetailSheet({
  batch,
  isOpen,
  onClose,
  userId,
  workspaceId,
  onRetryBatch,
  isRetrying,
}: ImportBatchDetailSheetProps) {
  const [failures, setFailures] = useState<ImportFailureItem[]>([])
  const [isLoadingFailures, setIsLoadingFailures] = useState(false)
  const [failureError, setFailureError] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)

  const loadFailures = useCallback(
    async (batchId: string, pageNum: number) => {
      try {
        setIsLoadingFailures(true)
        setFailureError(null)
        const res = await importGateway.getFailures(batchId, userId, workspaceId, {
          page: pageNum,
          perPage: 50,
        })
        setFailures(res.items)
        setTotalPages(res.total_pages)
        setPage(res.page)
      } catch (err) {
        setFailureError(
          err instanceof Error ? err.message : 'Не удалось загрузить список ошибок',
        )
      } finally {
        setIsLoadingFailures(false)
      }
    },
    [userId, workspaceId],
  )

  useEffect(() => {
    let ignore = false
    void Promise.resolve().then(() => {
      if (ignore) return
      if (batch && isOpen && batch.failed_rows > 0) {
        void loadFailures(batch.id, 1)
      } else {
        setFailures([])
        setPage(1)
        setTotalPages(1)
      }
    })
    return () => {
      ignore = true
    }
  }, [batch, isOpen, loadFailures])

  if (!batch) return null

  const canRetry = batch.status === 'failed' || batch.status === 'completed_with_errors'

  return (
    <Sheet open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <SheetContent
        side="right"
        className="w-full sm:max-w-2xl overflow-y-auto p-6 space-y-6"
      >
        <SheetHeader className="space-y-1">
          <div className="flex items-center justify-between gap-2 pr-6">
            <SheetTitle className="text-xl font-bold truncate">
              {batch.original_filename}
            </SheetTitle>
            <ImportStatusBadge status={batch.status} />
          </div>
          <SheetDescription className="text-xs text-muted-foreground">
            ID: {batch.id} • Набор:{' '}
            {batch.dataset_type === 'sales' ? 'Продажи' : 'Остатки на складе'}
          </SheetDescription>
        </SheetHeader>

        {/* Сводные KPI карточки */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <Card className="p-3 bg-secondary/30 border-border/60">
            <CardContent className="p-0">
              <span className="text-[11px] text-muted-foreground uppercase font-semibold flex items-center gap-1">
                <Layers className="h-3 w-3" />
                Всего строк
              </span>
              <p className="text-lg font-bold mt-1 text-foreground">
                {batch.total_rows || batch.processed_rows}
              </p>
            </CardContent>
          </Card>

          <Card className="p-3 bg-emerald-500/5 border-emerald-500/20">
            <CardContent className="p-0">
              <span className="text-[11px] text-emerald-500 uppercase font-semibold flex items-center gap-1">
                <CheckCircle2 className="h-3 w-3" />
                Успешно
              </span>
              <p className="text-lg font-bold mt-1 text-emerald-400">
                {batch.successful_rows}
              </p>
            </CardContent>
          </Card>

          <Card className="p-3 bg-rose-500/5 border-rose-500/20">
            <CardContent className="p-0">
              <span className="text-[11px] text-rose-500 uppercase font-semibold flex items-center gap-1">
                <AlertTriangle className="h-3 w-3" />
                Ошибок
              </span>
              <p className="text-lg font-bold mt-1 text-rose-400">{batch.failed_rows}</p>
            </CardContent>
          </Card>

          <Card className="p-3 bg-secondary/30 border-border/60">
            <CardContent className="p-0">
              <span className="text-[11px] text-muted-foreground uppercase font-semibold flex items-center gap-1">
                <Clock className="h-3 w-3" />
                Создан
              </span>
              <p className="text-xs font-medium mt-1.5 text-foreground truncate">
                {new Date(batch.created_at).toLocaleTimeString('ru-RU', {
                  hour: '2-digit',
                  minute: '2-digit',
                  second: '2-digit',
                })}
              </p>
            </CardContent>
          </Card>
        </div>

        {batch.error_message && (
          <div className="p-3 rounded-lg bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-start gap-2">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
            <div>
              <p className="font-semibold">Ошибка обработки пакета</p>
              <p className="mt-0.5">{batch.error_message}</p>
            </div>
          </div>
        )}

        {/* Ошибки валидации строк */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
              <AlertTriangle className="h-4 w-4 text-amber-500" />
              Ошибки валидации ({batch.failed_rows})
            </h4>
            {canRetry && (
              <Button
                size="sm"
                onClick={() => onRetryBatch(batch.id)}
                disabled={isRetrying}
                className="h-8 text-xs bg-amber-600 hover:bg-amber-700 text-white font-medium"
              >
                <RefreshCw
                  className={`h-3 w-3 mr-1.5 ${isRetrying ? 'animate-spin' : ''}`}
                />
                Повторить импорт
              </Button>
            )}
          </div>

          {isLoadingFailures ? (
            <div className="flex justify-center py-8 text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin" />
            </div>
          ) : failureError ? (
            <p className="text-xs text-destructive p-3 rounded bg-destructive/10">
              {failureError}
            </p>
          ) : failures.length === 0 ? (
            <div className="py-6 text-center text-xs text-muted-foreground border border-dashed rounded-lg">
              {batch.failed_rows === 0
                ? 'Все строки успешно прошли валидацию и спроецированы.'
                : 'Нет записей об ошибках.'}
            </div>
          ) : (
            <div className="rounded-lg border border-border/80 overflow-hidden">
              <Table>
                <TableHeader className="bg-muted/40">
                  <TableRow>
                    <TableHead className="w-[80px]">Строка</TableHead>
                    <TableHead className="w-[120px]">Поле</TableHead>
                    <TableHead className="w-[120px]">Значение</TableHead>
                    <TableHead>Причина ошибки</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {failures.map((f) => (
                    <TableRow key={f.id} className="text-xs">
                      <TableCell className="font-mono text-muted-foreground">
                        Строка {f.row_number}
                      </TableCell>
                      <TableCell className="font-mono font-medium text-foreground">
                        {f.field || '—'}
                      </TableCell>
                      <TableCell
                        className="font-mono text-muted-foreground truncate max-w-[120px]"
                        title={f.value ?? ''}
                      >
                        {f.value || '—'}
                      </TableCell>
                      <TableCell className="text-rose-400">{f.error_message}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {totalPages > 1 && (
                <div className="flex items-center justify-between p-2.5 bg-muted/20 border-t border-border/60 text-xs">
                  <span className="text-muted-foreground">
                    Страница {page} из {totalPages}
                  </span>
                  <div className="flex gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={page <= 1}
                      onClick={() => loadFailures(batch.id, page - 1)}
                      className="h-7 text-xs px-2"
                    >
                      Назад
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={page >= totalPages}
                      onClick={() => loadFailures(batch.id, page + 1)}
                      className="h-7 text-xs px-2"
                    >
                      Вперёд
                    </Button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
