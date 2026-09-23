import React from 'react'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DataIngestionView } from './data-ingestion-view'
import { importGateway } from '../api/import-gateway'
import type { ImportBatchSummary } from '../api/import-gateway'

vi.mock('../api/import-gateway', () => ({
  importGateway: {
    getBatches: vi.fn(),
    uploadBatch: vi.fn(),
    getBatch: vi.fn(),
    getFailures: vi.fn(),
    retryBatch: vi.fn(),
  },
}))

describe('DataIngestionView E2E Flow', () => {
  const initialBatch: ImportBatchSummary = {
    id: 'batch-active',
    workspace_id: 'ws-1',
    dataset_type: 'sales',
    source_format: 'csv',
    original_filename: 'sales_running.csv',
    status: 'processing',
    total_rows: 100,
    processed_rows: 50,
    successful_rows: 50,
    failed_rows: 0,
    progress_percentage: 50,
    created_at: '2026-09-23T10:00:00Z',
  }

  const completedBatch: ImportBatchSummary = {
    ...initialBatch,
    status: 'completed',
    processed_rows: 100,
    successful_rows: 100,
    progress_percentage: 100,
  }

  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('loads batch list and polls until processing completes', async () => {
    vi.mocked(importGateway.getBatches)
      .mockResolvedValueOnce({
        items: [initialBatch],
        total: 1,
        page: 1,
        per_page: 20,
        total_pages: 1,
      })
      .mockResolvedValueOnce({
        items: [completedBatch],
        total: 1,
        page: 1,
        per_page: 20,
        total_pages: 1,
      })

    render(<DataIngestionView userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('sales_running.csv')).toBeDefined()
      expect(screen.getByText('50%')).toBeDefined()
    })

    // Advance timer for polling
    vi.advanceTimersByTime(3500)

    await waitFor(() => {
      expect(importGateway.getBatches).toHaveBeenCalledTimes(2)
      expect(screen.getByText('100%')).toBeDefined()
    })
  })

  it('uploads a new batch and refreshes the list', async () => {
    vi.mocked(importGateway.getBatches).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      per_page: 20,
      total_pages: 0,
    })

    vi.mocked(importGateway.uploadBatch).mockResolvedValueOnce({
      id: 'batch-new',
      workspace_id: 'ws-1',
      dataset_type: 'sales',
      source_format: 'csv',
      original_filename: 'new_upload.csv',
      status: 'pending',
      total_rows: 0,
      processed_rows: 0,
      successful_rows: 0,
      failed_rows: 0,
      stored_file_path: '/path/file.csv',
      created_at: '2026-09-23T10:00:00Z',
    })

    render(<DataIngestionView userId="user-1" workspaceId="ws-1" />)

    const file = new File(['sku,qty\nSKU1,5'], 'new_upload.csv', { type: 'text/csv' })
    const input = screen.getByTestId('file-input')
    fireEvent.change(input, { target: { files: [file] } })

    const uploadBtn = screen.getByRole('button', { name: /Начать импорт/i })
    fireEvent.click(uploadBtn)

    await waitFor(() => {
      expect(importGateway.uploadBatch).toHaveBeenCalledWith(
        'user-1',
        'ws-1',
        file,
        'sales',
      )
    })
  })

  it('opens batch detail sheet when clicking view button', async () => {
    vi.mocked(importGateway.getBatches).mockResolvedValue({
      items: [completedBatch],
      total: 1,
      page: 1,
      per_page: 20,
      total_pages: 1,
    })
    vi.mocked(importGateway.getFailures).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      per_page: 50,
      total_pages: 0,
    })

    render(<DataIngestionView userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('sales_running.csv')).toBeDefined()
    })

    const viewBtn = screen.getByRole('button', { name: /Детали/i })
    fireEvent.click(viewBtn)

    await waitFor(() => {
      expect(screen.getByText(/Всего строк/i)).toBeDefined()
      expect(screen.getByText(/ID: batch-active/i)).toBeDefined()
    })
  })

  it('triggers retry for a failed batch', async () => {
    const failedBatch: ImportBatchSummary = {
      ...initialBatch,
      id: 'batch-failed-1',
      status: 'failed',
      progress_percentage: 0,
    }
    vi.mocked(importGateway.getBatches).mockResolvedValue({
      items: [failedBatch],
      total: 1,
      page: 1,
      per_page: 20,
      total_pages: 1,
    })
    vi.mocked(importGateway.retryBatch).mockResolvedValueOnce({
      id: 'batch-failed-1',
      workspace_id: 'ws-1',
      dataset_type: 'sales',
      source_format: 'csv',
      original_filename: 'sales_running.csv',
      status: 'pending',
      total_rows: 100,
      processed_rows: 0,
      successful_rows: 0,
      failed_rows: 0,
      stored_file_path: '/path/file.csv',
      created_at: '2026-09-23T10:00:00Z',
    })

    render(<DataIngestionView userId="user-1" workspaceId="ws-1" />)

    await waitFor(() => {
      expect(screen.getByText('sales_running.csv')).toBeDefined()
    })

    const retryBtn = screen.getByRole('button', { name: /Повторить/i })
    fireEvent.click(retryBtn)

    await waitFor(() => {
      expect(importGateway.retryBatch).toHaveBeenCalledWith(
        'batch-failed-1',
        'user-1',
        'ws-1',
      )
    })
  })
})
