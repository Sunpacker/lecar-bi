'use client'

import React, { useState, useRef } from 'react'
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { UploadCloud, FileSpreadsheet, AlertCircle, Loader2 } from 'lucide-react'
import type { DatasetType } from '../api/import-gateway'

interface ImportUploadDropzoneProps {
  onUpload: (file: File, datasetType: DatasetType) => Promise<void>
  isUploading: boolean
}

export function ImportUploadDropzone({
  onUpload,
  isUploading,
}: ImportUploadDropzoneProps) {
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [datasetType, setDatasetType] = useState<DatasetType>('sales')
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [isDragOver, setIsDragOver] = useState(false)
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  const validateAndSetFile = (file: File) => {
    setErrorMessage(null)
    const ext = file.name.split('.').pop()?.toLowerCase()
    if (!ext || !['csv', 'json', 'jsonl'].includes(ext)) {
      setErrorMessage('Поддерживаются только файлы .csv, .json или .jsonl')
      setSelectedFile(null)
      return
    }
    if (file.size > 50 * 1024 * 1024) {
      setErrorMessage('Размер файла не должен превышать 50 МБ')
      setSelectedFile(null)
      return
    }
    setSelectedFile(file)
  }

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      validateAndSetFile(e.target.files[0])
    }
  }

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    setIsDragOver(false)
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      validateAndSetFile(e.dataTransfer.files[0])
    }
  }

  const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    setIsDragOver(true)
  }

  const handleDragLeave = () => {
    setIsDragOver(false)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedFile) return

    try {
      setErrorMessage(null)
      await onUpload(selectedFile, datasetType)
      setSelectedFile(null)
      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Ошибка при загрузке файла')
    }
  }

  return (
    <Card className="border-border/60 bg-card/60 backdrop-blur-sm">
      <CardHeader>
        <CardTitle className="text-xl flex items-center gap-2">
          <UploadCloud className="h-5 w-5 text-emerald-500" />
          Загрузка данных
        </CardTitle>
        <CardDescription>
          Загрузите CSV или JSON файл для потокового импорта в хранилище и автоматической
          проекции в Star Schema аналитики.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label
                htmlFor="dataset-type-select"
                className="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
              >
                Тип набора данных
              </label>
              <select
                id="dataset-type-select"
                value={datasetType}
                onChange={(e) => setDatasetType(e.target.value as DatasetType)}
                className="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                disabled={isUploading}
              >
                <option value="sales">Продажи (Orders & Items)</option>
                <option value="inventory">Складские остатки (Daily Snapshots)</option>
              </select>
            </div>
          </div>

          <div
            onDrop={handleDrop}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onClick={() => fileInputRef.current?.click()}
            className={`border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors ${
              isDragOver
                ? 'border-emerald-500 bg-emerald-500/5'
                : 'border-border/80 hover:border-emerald-500/50 hover:bg-accent/40'
            }`}
          >
            <input
              ref={fileInputRef}
              type="file"
              data-testid="file-input"
              accept=".csv,.json,.jsonl"
              onChange={handleFileChange}
              className="hidden"
              disabled={isUploading}
            />

            <div className="flex flex-col items-center justify-center space-y-2">
              <div className="p-3 bg-secondary/80 rounded-full">
                <FileSpreadsheet className="h-6 w-6 text-emerald-500" />
              </div>
              {selectedFile ? (
                <div>
                  <p className="font-semibold text-foreground text-sm">
                    {selectedFile.name}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {(selectedFile.size / 1024).toFixed(1)} КБ
                  </p>
                </div>
              ) : (
                <div>
                  <p className="text-sm font-medium text-foreground">
                    Перетащите файл сюда или нажмите для выбора
                  </p>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    CSV, JSON или JSONL (до 50 МБ)
                  </p>
                </div>
              )}
            </div>
          </div>

          {errorMessage && (
            <div className="flex items-center gap-2 p-3 text-xs rounded-md bg-destructive/10 text-destructive border border-destructive/20">
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{errorMessage}</span>
            </div>
          )}

          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={!selectedFile || isUploading}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-medium"
            >
              {isUploading ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Загрузка...
                </>
              ) : (
                'Начать импорт'
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
