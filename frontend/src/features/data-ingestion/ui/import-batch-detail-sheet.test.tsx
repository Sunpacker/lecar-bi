import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { ImportBatchDetailSheet } from './import-batch-detail-sheet'
import { importGateway } from '../api/import-gateway'
import type { ImportBatchSummary } from '../api/import-gateway'

vi.mock('../api/import-gateway', () => ({
  importGateway: {
    getFailures: vi.fn(),
  },
}))

describe('ImportBatchDetailSheet', () => {
  const mockBatch: ImportBatchSummary = {
    id: 'batch-detail-1',
    workspace_id: 'ws-1',
    dataset_type: 'sales',
    source_format: 'csv',
    original_filename: 'sales_q1.csv',
    status: 'completed_with_errors',
    total_rows: 100,
    processed_rows: 100,
    successful_rows: 95,
    failed_rows: 5,
    progress_percentage: 100,
    error_message: null,
    created_at: '2026-09-23T10:00:00Z',
    completed_at: '2026-09-23T10:02:00Z',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders batch overview cards and loads failure rows from API', async () => {
    vi.mocked(importGateway.getFailures).mockResolvedValueOnce({
      items: [
        {
          id: 'fail-1',
          row_number: 14,
          field: 'unit_price',
          value: 'INVALID',
          error_message: 'Значение должно быть числом',
          created_at: '2026-09-23T10:01:00Z',
        },
      ],
      total: 1,
      page: 1,
      per_page: 50,
      total_pages: 1,
    })

    render(
      <ImportBatchDetailSheet
        batch={mockBatch}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={vi.fn()}
        isRetrying={false}
      />,
    )

    expect(screen.getByText('sales_q1.csv')).toBeDefined()
    expect(screen.getByText('95')).toBeDefined()
    expect(screen.getByText('5')).toBeDefined()

    await waitFor(() => {
      expect(importGateway.getFailures).toHaveBeenCalledWith(
        'batch-detail-1',
        'user-1',
        'ws-1',
        {
          page: 1,
          perPage: 50,
        },
      )
    })

    expect(screen.getByText('Строка 14')).toBeDefined()
    expect(screen.getByText('unit_price')).toBeDefined()
    expect(screen.getByText('Значение должно быть числом')).toBeDefined()
  })

  it('allows retrying batch directly from the detail sheet', () => {
    const onRetry = vi.fn()
    vi.mocked(importGateway.getFailures).mockResolvedValueOnce({
      items: [],
      total: 0,
      page: 1,
      per_page: 50,
      total_pages: 0,
    })

    render(
      <ImportBatchDetailSheet
        batch={mockBatch}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={onRetry}
        isRetrying={false}
      />,
    )

    const retryBtn = screen.getByRole('button', { name: /Повторить импорт/i })
    fireEvent.click(retryBtn)

    expect(onRetry).toHaveBeenCalledWith('batch-detail-1')
  })

  it('does not call getFailures if failed_rows is 0', async () => {
    render(
      <ImportBatchDetailSheet
        batch={{ ...mockBatch, status: 'completed', failed_rows: 0 }}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={vi.fn()}
        isRetrying={false}
      />,
    )

    expect(
      screen.getByText('Все строки успешно прошли валидацию и спроецированы.'),
    ).toBeDefined()
    expect(importGateway.getFailures).not.toHaveBeenCalled()
  })

  it('does not display retry button if batch is completed', () => {
    render(
      <ImportBatchDetailSheet
        batch={{ ...mockBatch, status: 'completed', failed_rows: 0 }}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={vi.fn()}
        isRetrying={false}
      />,
    )

    expect(screen.queryByRole('button', { name: /Повторить импорт/i })).toBeNull()
  })

  it('displays error_message when present on batch', () => {
    render(
      <ImportBatchDetailSheet
        batch={{
          ...mockBatch,
          status: 'failed',
          error_message: 'Неверная кодировка файла',
        }}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={vi.fn()}
        isRetrying={false}
      />,
    )

    expect(screen.getByText('Ошибка обработки пакета')).toBeDefined()
    expect(screen.getByText('Неверная кодировка файла')).toBeDefined()
  })

  it('handles pagination next button click to load next page', async () => {
    vi.mocked(importGateway.getFailures)
      .mockResolvedValueOnce({
        items: [
          {
            id: 'fail-1',
            row_number: 1,
            field: 'amount',
            value: '0',
            error_message: 'Должно быть больше 0',
            created_at: '2026-09-23T10:01:00Z',
          },
        ],
        total: 100,
        page: 1,
        per_page: 50,
        total_pages: 2,
      })
      .mockResolvedValueOnce({
        items: [
          {
            id: 'fail-2',
            row_number: 51,
            field: 'amount',
            value: '-5',
            error_message: 'Должно быть больше 0',
            created_at: '2026-09-23T10:01:00Z',
          },
        ],
        total: 100,
        page: 2,
        per_page: 50,
        total_pages: 2,
      })

    render(
      <ImportBatchDetailSheet
        batch={mockBatch}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={vi.fn()}
        isRetrying={false}
      />,
    )

    await waitFor(() => {
      expect(screen.getByText('Страница 1 из 2')).toBeDefined()
    })

    const nextBtn = screen.getByRole('button', { name: 'Вперёд' })
    fireEvent.click(nextBtn)

    await waitFor(() => {
      expect(importGateway.getFailures).toHaveBeenCalledWith(
        'batch-detail-1',
        'user-1',
        'ws-1',
        { page: 2, perPage: 50 },
      )
      expect(screen.getByText('Страница 2 из 2')).toBeDefined()
    })
  })

  it('does not refetch page 1 failures when batch prop reference changes during polling with same batch id', async () => {
    vi.mocked(importGateway.getFailures).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      per_page: 50,
      total_pages: 1,
    })

    const { rerender } = render(
      <ImportBatchDetailSheet
        batch={mockBatch}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={vi.fn()}
        isRetrying={false}
      />,
    )

    await waitFor(() => {
      expect(importGateway.getFailures).toHaveBeenCalledTimes(1)
    })

    // Simulate parent polling providing a new batch object reference
    rerender(
      <ImportBatchDetailSheet
        batch={{ ...mockBatch, processed_rows: 100 }}
        isOpen={true}
        onClose={vi.fn()}
        userId="user-1"
        workspaceId="ws-1"
        onRetryBatch={vi.fn()}
        isRetrying={false}
      />,
    )

    // Should still have been called only once, without triggering an extra refetch
    expect(importGateway.getFailures).toHaveBeenCalledTimes(1)
  })
})
