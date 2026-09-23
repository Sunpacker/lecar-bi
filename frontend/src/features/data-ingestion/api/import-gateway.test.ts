import { describe, it, expect, vi, beforeEach } from 'vitest'
import { importGateway } from './import-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
    POST: vi.fn(),
  },
}))

describe('importGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches list of import batches with filters and headers', async () => {
    const mockList = {
      items: [
        {
          id: 'batch-1',
          workspace_id: 'ws-1',
          dataset_type: 'sales' as const,
          source_format: 'csv' as const,
          original_filename: 'sales-january.csv',
          status: 'completed' as const,
          total_rows: 100,
          processed_rows: 100,
          successful_rows: 98,
          failed_rows: 2,
          progress_percentage: 100,
          created_at: '2026-09-23T10:00:00Z',
        },
      ],
      total: 1,
      page: 1,
      per_page: 20,
      total_pages: 1,
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockList,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await importGateway.getBatches('user-1', 'ws-1', {
      page: 1,
      perPage: 20,
      status: 'completed',
      datasetType: 'sales',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith('/imports', {
      params: {
        query: {
          page: 1,
          per_page: 20,
          status: 'completed',
          dataset_type: 'sales',
        },
      },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockList)
  })

  it('uploads a file with FormData and dataset_type', async () => {
    const mockDetail = {
      batch: {
        id: 'batch-new',
        workspace_id: 'ws-1',
        dataset_type: 'inventory' as const,
        source_format: 'csv' as const,
        original_filename: 'stock.csv',
        status: 'pending' as const,
        total_rows: 0,
        processed_rows: 0,
        successful_rows: 0,
        failed_rows: 0,
        progress_percentage: 0,
        stored_file_path: '/storage/imports/ws-1/stock.csv',
        created_at: '2026-09-23T10:05:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockDetail,
      error: undefined,
      response: new Response(),
    } as never)

    const file = new File(['sku,qty\nSKU1,10'], 'stock.csv', { type: 'text/csv' })
    const result = await importGateway.uploadBatch('user-1', 'ws-1', file, 'inventory')

    expect(analyticsClient.POST).toHaveBeenCalledWith(
      '/imports',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
      }),
    )
    expect(result).toEqual(mockDetail.batch)
  })

  it('fetches single batch details by ID', async () => {
    const mockDetail = {
      batch: {
        id: 'batch-1',
        workspace_id: 'ws-1',
        dataset_type: 'sales' as const,
        source_format: 'csv' as const,
        original_filename: 'sales.csv',
        status: 'processing' as const,
        total_rows: 1000,
        processed_rows: 500,
        successful_rows: 490,
        failed_rows: 10,
        progress_percentage: 50,
        stored_file_path: '/storage/imports/ws-1/sales.csv',
        created_at: '2026-09-23T10:00:00Z',
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockDetail,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await importGateway.getBatch('batch-1', 'user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith('/imports/{id}', {
      params: { path: { id: 'batch-1' } },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockDetail.batch)
  })

  it('fetches validation failure rows with pagination', async () => {
    const mockFailures = {
      items: [
        {
          id: 'fail-1',
          row_number: 42,
          field: 'unit_price',
          value: '-50',
          error_message: 'Цена не может быть отрицательной',
          created_at: '2026-09-23T10:01:00Z',
        },
      ],
      total: 1,
      page: 1,
      per_page: 50,
      total_pages: 1,
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockFailures,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await importGateway.getFailures('batch-1', 'user-1', 'ws-1', {
      page: 1,
      perPage: 50,
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith('/imports/{id}/failures', {
      params: {
        path: { id: 'batch-1' },
        query: { page: 1, per_page: 50 },
      },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockFailures)
  })

  it('retries a failed or partially completed batch', async () => {
    const mockRetried = {
      batch: {
        id: 'batch-1',
        workspace_id: 'ws-1',
        dataset_type: 'sales' as const,
        source_format: 'csv' as const,
        original_filename: 'sales.csv',
        status: 'pending' as const,
        total_rows: 0,
        processed_rows: 0,
        successful_rows: 0,
        failed_rows: 0,
        stored_file_path: '/storage/imports/ws-1/sales.csv',
        created_at: '2026-09-23T10:00:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockRetried,
      error: undefined,
      response: new Response(),
    } as never)

    const result = await importGateway.retryBatch('batch-1', 'user-1', 'ws-1')

    expect(analyticsClient.POST).toHaveBeenCalledWith('/imports/{id}/retry', {
      params: { path: { id: 'batch-1' } },
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    })
    expect(result).toEqual(mockRetried.batch)
  })
})
