'use client'

import React from 'react'
import {
  Table,
  TableHeader,
  TableBody,
  TableHead,
  TableRow,
  TableCell,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Progress, ProgressTrack, ProgressIndicator } from '@/components/ui/progress'
import { RefreshCw, Eye, AlertCircle } from 'lucide-react'
import { ImportStatusBadge } from './import-status-badge'
import type { ImportBatchSummary } from '../api/import-gateway'

interface ImportBatchListProps {
  batches: ImportBatchSummary[]
  onSelectBatch: (batch: ImportBatchSummary) => void
  onRetryBatch: (batchId: string) => Promise<void>
  retryingBatchId: string | null
  canRetry?: boolean
}

export function ImportBatchList({
  batches,
  onSelectBatch,
  onRetryBatch,
  retryingBatchId,
  canRetry: canRetryAllowed = true,
}: ImportBatchListProps) {
  if (batches.length === 0) {
    return (
      <div className="text-center py-12 border border-dashed rounded-xl border-border/80">
        <p className="text-muted-foreground text-sm">Нет загруженных наборов данных.</p>
        <p className="text-xs text-muted-foreground/70 mt-1">
          Загрузите первый CSV-файл выше для начала обработки.
        </p>
      </div>
    )
  }

  return (
    <div className="rounded-xl border border-border/70 overflow-hidden bg-card/60 backdrop-blur-sm">
      <Table>
        <TableHeader className="bg-muted/40">
          <TableRow>
            <TableHead className="w-[180px]">Дата и время</TableHead>
            <TableHead>Файл</TableHead>
            <TableHead className="w-[120px]">Датасет</TableHead>
            <TableHead className="w-[140px]">Статус</TableHead>
            <TableHead className="w-[180px]">Прогресс строк</TableHead>
            <TableHead className="text-right w-[160px]">Действия</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {batches.map((batch) => {
            const isRetrying = retryingBatchId === batch.id
            const canRetry =
              canRetryAllowed &&
              (batch.status === 'failed' || batch.status === 'completed_with_errors')
            const percent =
              batch.progress_percentage ??
              (batch.total_rows > 0
                ? Math.round((batch.processed_rows / batch.total_rows) * 100)
                : 0)

            return (
              <TableRow key={batch.id} className="hover:bg-accent/30 transition-colors">
                <TableCell className="text-xs text-muted-foreground whitespace-nowrap">
                  {new Date(batch.created_at).toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </TableCell>
                <TableCell className="font-medium text-foreground text-sm">
                  <div className="flex flex-col">
                    <span className="truncate max-w-[240px] sm:max-w-xs">
                      {batch.original_filename}
                    </span>
                    {batch.error_message && (
                      <span className="text-[11px] text-destructive truncate max-w-[240px] flex items-center gap-1 mt-0.5">
                        <AlertCircle className="h-3 w-3 shrink-0" />
                        {batch.error_message}
                      </span>
                    )}
                  </div>
                </TableCell>
                <TableCell className="text-xs text-muted-foreground uppercase font-medium">
                  {batch.dataset_type === 'sales' ? 'Продажи' : 'Склад'}
                </TableCell>
                <TableCell>
                  <ImportStatusBadge status={batch.status} />
                </TableCell>
                <TableCell>
                  <div className="space-y-1">
                    <div className="flex justify-between text-[11px] text-muted-foreground">
                      <span>{percent}%</span>
                      <span>
                        {batch.successful_rows} /{' '}
                        {batch.total_rows || batch.processed_rows}
                        {batch.failed_rows > 0 && (
                          <span className="text-rose-500 font-medium ml-1">
                            ({batch.failed_rows} ош.)
                          </span>
                        )}
                      </span>
                    </div>
                    <Progress value={percent} className="h-1.5 w-full">
                      <ProgressTrack className="bg-secondary/60">
                        <ProgressIndicator
                          className={
                            batch.status === 'failed'
                              ? 'bg-rose-500'
                              : batch.status === 'completed_with_errors'
                                ? 'bg-amber-500'
                                : 'bg-emerald-500'
                          }
                        />
                      </ProgressTrack>
                    </Progress>
                  </div>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex items-center justify-end gap-1.5">
                    {canRetry && (
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => onRetryBatch(batch.id)}
                        disabled={isRetrying}
                        className="h-8 px-2 text-xs text-amber-500 hover:text-amber-400 hover:bg-amber-500/10"
                        title="Повторить импорт"
                      >
                        <RefreshCw
                          className={`h-3.5 w-3.5 mr-1 ${isRetrying ? 'animate-spin' : ''}`}
                        />
                        Повторить
                      </Button>
                    )}
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => onSelectBatch(batch)}
                      className="h-8 px-2.5 text-xs gap-1 border-border/80"
                    >
                      <Eye className="h-3.5 w-3.5" />
                      Детали
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            )
          })}
        </TableBody>
      </Table>
    </div>
  )
}
