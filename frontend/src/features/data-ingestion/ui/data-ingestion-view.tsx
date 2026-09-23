'use client'

import React, { useState, useEffect, useCallback, useRef } from 'react'
import { ImportUploadDropzone } from './import-upload-dropzone'
import { ImportBatchList } from './import-batch-list'
import { ImportBatchDetailSheet } from './import-batch-detail-sheet'
import { importGateway } from '../api/import-gateway'
import type { ImportBatchSummary, DatasetType, ImportStatus } from '../api/import-gateway'
import { Button } from '@/components/ui/button'
import { RefreshCw, Filter } from 'lucide-react'

interface DataIngestionViewProps {
  userId: string
  workspaceId: string
}

export function DataIngestionView({ userId, workspaceId }: DataIngestionViewProps) {
  const [batches, setBatches] = useState<ImportBatchSummary[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isUploading, setIsUploading] = useState(false)
  const [retryingBatchId, setRetryingBatchId] = useState<string | null>(null)
  const [selectedBatch, setSelectedBatch] = useState<ImportBatchSummary | null>(null)
  const [isSheetOpen, setIsSheetOpen] = useState(false)
  const [statusFilter, setStatusFilter] = useState<ImportStatus | 'all'>('all')
  const [datasetFilter, setDatasetFilter] = useState<DatasetType | 'all'>('all')

  const pollingRef = useRef<NodeJS.Timeout | null>(null)

  const loadBatches = useCallback(
    async (silent = false) => {
      try {
        if (!silent) setIsLoading(true)
        const res = await importGateway.getBatches(userId, workspaceId, {
          page: 1,
          perPage: 50,
          status: statusFilter === 'all' ? undefined : statusFilter,
          datasetType: datasetFilter === 'all' ? undefined : datasetFilter,
        })
        setBatches(res.items)

        // If detailed sheet is open, update selected batch
        setSelectedBatch((prev) => {
          if (!prev) return null
          const updated = res.items.find((b) => b.id === prev.id)
          return updated ?? prev
        })
      } catch (err) {
        console.error('Failed to load import batches', err)
      } finally {
        if (!silent) setIsLoading(false)
      }
    },
    [userId, workspaceId, statusFilter, datasetFilter],
  )

  // Initial load and filter change
  useEffect(() => {
    let ignore = false
    void Promise.resolve().then(() => {
      if (!ignore) {
        void loadBatches()
      }
    })
    return () => {
      ignore = true
    }
  }, [loadBatches])

  // Polling for active imports
  useEffect(() => {
    const hasActiveBatches = batches.some((b) =>
      ['pending', 'validating', 'processing'].includes(b.status),
    )

    if (hasActiveBatches) {
      pollingRef.current = setInterval(() => {
        loadBatches(true)
      }, 3000)
    } else if (pollingRef.current) {
      clearInterval(pollingRef.current)
      pollingRef.current = null
    }

    return () => {
      if (pollingRef.current) {
        clearInterval(pollingRef.current)
        pollingRef.current = null
      }
    }
  }, [batches, loadBatches])

  const handleUpload = async (file: File, datasetType: DatasetType) => {
    try {
      setIsUploading(true)
      await importGateway.uploadBatch(userId, workspaceId, file, datasetType)
      await loadBatches(true)
    } finally {
      setIsUploading(false)
    }
  }

  const handleRetry = async (batchId: string) => {
    try {
      setRetryingBatchId(batchId)
      await importGateway.retryBatch(batchId, userId, workspaceId)
      await loadBatches(true)
    } catch (err) {
      console.error('Retry failed', err)
    } finally {
      setRetryingBatchId(null)
    }
  }

  const handleSelectBatch = (batch: ImportBatchSummary) => {
    setSelectedBatch(batch)
    setIsSheetOpen(true)
  }

  return (
    <div className="space-y-6">
      <ImportUploadDropzone onUpload={handleUpload} isUploading={isUploading} />

      <div className="space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-bold text-foreground">История загрузок</h2>
            <p className="text-xs text-muted-foreground">
              Пакеты импорта данных, их статус обработки и журнал ошибок валидации.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1.5">
              <Filter className="h-3.5 w-3.5 text-muted-foreground" />
              <select
                value={datasetFilter}
                onChange={(e) => setDatasetFilter(e.target.value as DatasetType | 'all')}
                className="h-8 rounded-md border border-input bg-background px-2 text-xs focus:outline-none"
              >
                <option value="all">Все датасеты</option>
                <option value="sales">Продажи</option>
                <option value="inventory">Склад</option>
              </select>

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as ImportStatus | 'all')}
                className="h-8 rounded-md border border-input bg-background px-2 text-xs focus:outline-none"
              >
                <option value="all">Все статусы</option>
                <option value="completed">Завершено</option>
                <option value="completed_with_errors">С ошибками</option>
                <option value="processing">В обработке</option>
                <option value="failed">Сбой</option>
              </select>
            </div>

            <Button
              size="sm"
              variant="outline"
              onClick={() => loadBatches()}
              disabled={isLoading}
              className="h-8 px-2.5 text-xs gap-1 border-border/80"
              title="Обновить список"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
              <span className="hidden sm:inline">Обновить</span>
            </Button>
          </div>
        </div>

        <ImportBatchList
          batches={batches}
          onSelectBatch={handleSelectBatch}
          onRetryBatch={handleRetry}
          retryingBatchId={retryingBatchId}
        />
      </div>

      <ImportBatchDetailSheet
        batch={selectedBatch}
        isOpen={isSheetOpen}
        onClose={() => setIsSheetOpen(false)}
        userId={userId}
        workspaceId={workspaceId}
        onRetryBatch={handleRetry}
        isRetrying={retryingBatchId === selectedBatch?.id}
      />
    </div>
  )
}
