import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { ImportStatusBadge } from './import-status-badge'
import { ImportUploadDropzone } from './import-upload-dropzone'
import { ImportBatchList } from './import-batch-list'
import type { ImportBatchSummary } from '../api/import-gateway'

describe('ImportStatusBadge', () => {
  it('renders correct labels and styling for all statuses', () => {
    const { rerender } = render(<ImportStatusBadge status="pending" />)
    expect(screen.getByText('В очереди')).toBeDefined()

    rerender(<ImportStatusBadge status="validating" />)
    expect(screen.getByText('Валидация')).toBeDefined()

    rerender(<ImportStatusBadge status="processing" />)
    expect(screen.getByText('Обработка')).toBeDefined()

    rerender(<ImportStatusBadge status="completed" />)
    expect(screen.getByText('Завершено')).toBeDefined()

    rerender(<ImportStatusBadge status="completed_with_errors" />)
    expect(screen.getByText('Есть ошибки')).toBeDefined()

    rerender(<ImportStatusBadge status="failed" />)
    expect(screen.getByText('Сбой')).toBeDefined()
  })
})

describe('ImportUploadDropzone', () => {
  it('renders dataset selection and file upload input', () => {
    render(<ImportUploadDropzone onUpload={vi.fn()} isUploading={false} />)
    expect(screen.getByText('Загрузка данных')).toBeDefined()
    expect(screen.getByLabelText(/Тип набора данных/i)).toBeDefined()
    expect(screen.getByText('Продажи (Orders & Items)')).toBeDefined()
  })

  it('triggers onUpload callback when valid file is selected and submitted', async () => {
    const onUpload = vi.fn().mockResolvedValue(undefined)
    render(<ImportUploadDropzone onUpload={onUpload} isUploading={false} />)

    const file = new File(['order_id,sku\n1,A100'], 'sales.csv', { type: 'text/csv' })
    const input = screen.getByTestId('file-input')

    fireEvent.change(input, { target: { files: [file] } })

    expect(screen.getByText('sales.csv')).toBeDefined()

    const submitBtn = screen.getByRole('button', { name: /Начать импорт/i })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(onUpload).toHaveBeenCalledWith(file, 'sales')
    })
  })

  it('validates unsupported file extension and displays error', () => {
    render(<ImportUploadDropzone onUpload={vi.fn()} isUploading={false} />)

    const file = new File(['text'], 'archive.zip', { type: 'application/zip' })
    const input = screen.getByTestId('file-input')

    fireEvent.change(input, { target: { files: [file] } })

    expect(
      screen.getByText(/Поддерживаются только файлы .csv, .json или .jsonl/i),
    ).toBeDefined()
  })
})

describe('ImportBatchList', () => {
  const mockBatches: ImportBatchSummary[] = [
    {
      id: 'batch-1',
      workspace_id: 'ws-1',
      dataset_type: 'sales',
      source_format: 'csv',
      original_filename: 'sales_2026.csv',
      status: 'completed',
      total_rows: 500,
      processed_rows: 500,
      successful_rows: 500,
      failed_rows: 0,
      progress_percentage: 100,
      created_at: '2026-09-23T10:00:00Z',
    },
    {
      id: 'batch-2',
      workspace_id: 'ws-1',
      dataset_type: 'inventory',
      source_format: 'csv',
      original_filename: 'stock_broken.csv',
      status: 'failed',
      total_rows: 100,
      processed_rows: 50,
      successful_rows: 40,
      failed_rows: 10,
      progress_percentage: 50,
      error_message: 'Критическая ошибка разбора схемы',
      created_at: '2026-09-23T10:05:00Z',
    },
    {
      id: 'batch-3',
      workspace_id: 'ws-1',
      dataset_type: 'sales',
      source_format: 'csv',
      original_filename: 'sales_active.csv',
      status: 'processing',
      total_rows: 1000,
      processed_rows: 450,
      successful_rows: 450,
      failed_rows: 0,
      progress_percentage: 45,
      created_at: '2026-09-23T10:10:00Z',
    },
  ]

  it('renders batch list rows with progress indicators and action buttons', () => {
    render(
      <ImportBatchList
        batches={mockBatches}
        onSelectBatch={vi.fn()}
        onRetryBatch={vi.fn()}
        retryingBatchId={null}
      />,
    )

    expect(screen.getByText('sales_2026.csv')).toBeDefined()
    expect(screen.getByText('stock_broken.csv')).toBeDefined()
    expect(screen.getByText('sales_active.csv')).toBeDefined()

    expect(screen.getByText('45%')).toBeDefined()
    expect(screen.getByText('100%')).toBeDefined()
  })

  it('allows clicking retry only on failed batches and triggers callback', () => {
    const onRetry = vi.fn().mockResolvedValue(undefined)
    render(
      <ImportBatchList
        batches={mockBatches}
        onSelectBatch={vi.fn()}
        onRetryBatch={onRetry}
        retryingBatchId={null}
      />,
    )

    const retryButtons = screen.getAllByRole('button', { name: /Повторить/i })
    expect(retryButtons.length).toBe(1)

    fireEvent.click(retryButtons[0])
    expect(onRetry).toHaveBeenCalledWith('batch-2')
  })

  it('triggers onSelectBatch when clicking row or details button', () => {
    const onSelect = vi.fn()
    render(
      <ImportBatchList
        batches={mockBatches}
        onSelectBatch={onSelect}
        onRetryBatch={vi.fn()}
        retryingBatchId={null}
      />,
    )

    const detailButtons = screen.getAllByRole('button', { name: /Детали/i })
    fireEvent.click(detailButtons[0])

    expect(onSelect).toHaveBeenCalledWith(mockBatches[0])
  })
})
