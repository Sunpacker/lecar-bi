# Data Ingestion Frontend UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the frontend UI, typed REST client, asynchronous polling, CSV/JSON upload dropzone, batch tracking list with progress bars, and validation failure inspection sheet for Phase 10 (Data Ingestion), enabling users to upload raw sales and inventory datasets, monitor background ingestion status in real time, inspect validation errors by row and field, and trigger safe retries for failed batches.

**Architecture:** Feature-oriented Next.js 16 frontend module under `frontend/src/features/data-ingestion` adhering to ADR-013 (no domain business rules on frontend) and ADR-016 (shadcn/ui + Tailwind CSS). The module communicates with Laravel Analytics backend via typed `importGateway` generated from OpenAPI 3.0.3 (`analytics-v1.yaml`). File upload uses `FormData` streaming to `/api/v1/imports`. Active batches in `pending`, `validating`, or `processing` state trigger automatic polling every 3 seconds until reaching terminal state (`completed`, `completed_with_errors`, `failed`). The slide-over `Sheet` displays batch KPIs and paginated failure rows from `/api/v1/imports/{id}/failures`.

**Tech Stack:** Next.js 16 (React 19 App Router), TypeScript 5.7, Tailwind CSS 4, shadcn/ui (@base-ui/react, lucide-react), openapi-fetch, Vitest, React Testing Library.

**Spec:** `docs/roadmap/10-data-ingestion.md`, `contracts/openapi/analytics-v1.yaml`, `docs/architecture/03-frontend-nextjs.md`, `docs/architecture/06-data-and-analytics.md`, `docs/architecture/07-api-and-integration.md`, `docs/architecture/12-architecture-decisions.md`.

## Global Constraints

- Frontend MUST NOT recalculate validation rules, parse CSV lines to alter domain meaning, or compute business metrics (ADR-013).
- UI components MUST strictly use shadcn/ui primitives (`Button`, `Card`, `Badge`, `Progress`, `Table`, `Sheet`, `Select`, `Input`, `Label`) and Tailwind CSS 4 (ADR-016).
- All API requests MUST use the typed API client generated from OpenAPI 3.0.3 (`frontend/src/shared/api/generated/schema.ts`).
- Multi-tenancy headers (`X-User-Id` and `X-Workspace-Id`) MUST be sent on every API invocation.
- Upload MUST support `.csv`, `.json`, `.jsonl` files up to 50 MB with dataset selection (`sales` or `inventory`).
- Polling MUST run strictly when there are active jobs (`pending`, `validating`, `processing`) and automatically stop when all batches reach terminal state (`completed`, `completed_with_errors`, `failed`) or on component unmount.
- Retry button MUST be enabled ONLY for batches in `failed` or `completed_with_errors` status.
- All code MUST pass `npm run typecheck`, `npm run lint`, and `npm test` with 0 errors.

---

### Task 1: Data Ingestion API Gateway & Types

**Files:**
- Create: `frontend/src/features/data-ingestion/api/import-gateway.ts`
- Create: `frontend/src/features/data-ingestion/api/import-gateway.test.ts`

**Interfaces:**
- Consumes:
  - `analyticsClient` from `frontend/src/shared/api/analytics-client`
  - Types `ImportBatchSummary`, `ImportBatchDetail`, `ImportBatchListResponse`, `ImportFailureItem`, `ImportFailureListResponse`, `ImportStatus`, `DatasetType` from `frontend/src/shared/api/generated/schema`
- Produces:
  - `importGateway.getBatches(userId: string, workspaceId?: string, params?: ImportBatchesQueryParams): Promise<ImportBatchListResponse>`
  - `importGateway.uploadBatch(userId: string, workspaceId: string, file: File, datasetType: DatasetType): Promise<ImportBatchDetail>`
  - `importGateway.getBatch(id: string, userId: string, workspaceId?: string): Promise<ImportBatchDetail>`
  - `importGateway.getFailures(id: string, userId: string, workspaceId?: string, params?: ImportFailuresQueryParams): Promise<ImportFailureListResponse>`
  - `importGateway.retryBatch(id: string, userId: string, workspaceId?: string): Promise<ImportBatchDetail>`

- [ ] **Step 1: Write failing tests in `frontend/src/features/data-ingestion/api/import-gateway.test.ts`**

```typescript
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

    expect(analyticsClient.POST).toHaveBeenCalledWith('/imports', expect.objectContaining({
      headers: {
        'X-User-Id': 'user-1',
        'X-Workspace-Id': 'ws-1',
      },
    }))
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test src/features/data-ingestion/api/import-gateway.test.ts`
Expected: FAIL with "Cannot find module './import-gateway'".

- [ ] **Step 3: Implement `frontend/src/features/data-ingestion/api/import-gateway.ts`**

```typescript
import { analyticsClient } from '../../../shared/api/analytics-client'
import type { components } from '../../../shared/api/generated/schema'

export type ImportBatchSummary = components['schemas']['ImportBatchSummary']
export type ImportBatchDetail = components['schemas']['ImportBatchDetail']
export type ImportBatchListResponse = components['schemas']['ImportBatchListResponse']
export type ImportFailureItem = components['schemas']['ImportFailureItem']
export type ImportFailureListResponse = components['schemas']['ImportFailureListResponse']
export type ImportStatus = components['schemas']['ImportStatus']
export type DatasetType = components['schemas']['DatasetType']

export interface ImportBatchesQueryParams {
  page?: number
  perPage?: number
  status?: ImportStatus
  datasetType?: DatasetType
}

export interface ImportFailuresQueryParams {
  page?: number
  perPage?: number
}

export const importGateway = {
  async getBatches(
    userId: string,
    workspaceId?: string,
    params?: ImportBatchesQueryParams,
  ): Promise<ImportBatchListResponse> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/imports', {
      params: {
        query: {
          page: params?.page,
          per_page: params?.perPage,
          status: params?.status,
          dataset_type: params?.datasetType,
        },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Не удалось загрузить список импортов')
    }

    return data
  },

  async uploadBatch(
    userId: string,
    workspaceId: string,
    file: File,
    datasetType: DatasetType,
  ): Promise<ImportBatchDetail> {
    const headers: Record<string, string> = {
      'X-User-Id': userId,
      'X-Workspace-Id': workspaceId,
    }

    const formData = new FormData()
    formData.append('file', file)
    formData.append('dataset_type', datasetType)

    const { data, error } = await analyticsClient.POST('/imports', {
      body: formData as unknown as {
        file: string
        dataset_type: DatasetType
      },
      bodySerializer: (body) => body as unknown as FormData,
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Не удалось загрузить файл импорта')
    }

    return data.batch
  },

  async getBatch(
    id: string,
    userId: string,
    workspaceId?: string,
  ): Promise<ImportBatchDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/imports/{id}', {
      params: {
        path: { id },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Не удалось получить данные импорта')
    }

    return data.batch
  },

  async getFailures(
    id: string,
    userId: string,
    workspaceId?: string,
    params?: ImportFailuresQueryParams,
  ): Promise<ImportFailureListResponse> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.GET('/imports/{id}/failures', {
      params: {
        path: { id },
        query: {
          page: params?.page,
          per_page: params?.perPage,
        },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Не удалось загрузить ошибки валидации импорта')
    }

    return data
  },

  async retryBatch(
    id: string,
    userId: string,
    workspaceId?: string,
  ): Promise<ImportBatchDetail> {
    const headers: Record<string, string> = { 'X-User-Id': userId }
    if (workspaceId) {
      headers['X-Workspace-Id'] = workspaceId
    }

    const { data, error } = await analyticsClient.POST('/imports/{id}/retry', {
      params: {
        path: { id },
      },
      headers,
    })

    if (error || !data) {
      throw new Error(error?.message ?? 'Не удалось запустить повторную обработку импорта')
    }

    return data.batch
  },
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test src/features/data-ingestion/api/import-gateway.test.ts`
Expected: PASS with 5 passing tests.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/data-ingestion/api/
git commit -m "feat(data-ingestion): add typed REST gateway client with tests"
```

---

### Task 2: Ingestion Core UI Components (Upload Dropzone, Status Badge, Batch List Table)

**Files:**
- Create: `frontend/src/features/data-ingestion/ui/import-status-badge.tsx`
- Create: `frontend/src/features/data-ingestion/ui/import-upload-dropzone.tsx`
- Create: `frontend/src/features/data-ingestion/ui/import-batch-list.tsx`
- Create: `frontend/src/features/data-ingestion/ui/import-components.test.tsx`

**Interfaces:**
- Consumes:
  - `ImportBatchSummary`, `ImportStatus`, `DatasetType` from `../api/import-gateway`
  - shadcn primitives: `Badge`, `Button`, `Card`, `Progress`, `Table`
- Produces:
  - `ImportStatusBadge({ status: ImportStatus })`
  - `ImportUploadDropzone({ onUpload: (file: File, datasetType: DatasetType) => Promise<void>, isUploading: boolean })`
  - `ImportBatchList({ batches: ImportBatchSummary[], onSelectBatch: (batch: ImportBatchSummary) => void, onRetryBatch: (batchId: string) => Promise<void>, retryingBatchId: string | null })`

- [ ] **Step 1: Write failing tests in `frontend/src/features/data-ingestion/ui/import-components.test.tsx`**

```typescript
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

    expect(screen.getByText(/Поддерживаются только файлы .csv, .json или .jsonl/i)).toBeDefined()
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test src/features/data-ingestion/ui/import-components.test.tsx`
Expected: FAIL with "Cannot find module './import-status-badge'".

- [ ] **Step 3: Implement `ImportStatusBadge`, `ImportUploadDropzone`, and `ImportBatchList`**

Create `frontend/src/features/data-ingestion/ui/import-status-badge.tsx`:
```tsx
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
        <Badge variant="outline" className="border-amber-500/40 text-amber-500 bg-amber-500/10 gap-1.5 font-medium">
          <Clock className="h-3 w-3" />
          В очереди
        </Badge>
      )
    case 'validating':
      return (
        <Badge variant="outline" className="border-sky-500/40 text-sky-500 bg-sky-500/10 gap-1.5 font-medium animate-pulse">
          <Loader2 className="h-3 w-3 animate-spin" />
          Валидация
        </Badge>
      )
    case 'processing':
      return (
        <Badge variant="outline" className="border-indigo-500/40 text-indigo-400 bg-indigo-500/10 gap-1.5 font-medium animate-pulse">
          <Loader2 className="h-3 w-3 animate-spin" />
          Обработка
        </Badge>
      )
    case 'completed':
      return (
        <Badge variant="outline" className="border-emerald-500/40 text-emerald-500 bg-emerald-500/10 gap-1.5 font-medium">
          <CheckCircle2 className="h-3 w-3" />
          Завершено
        </Badge>
      )
    case 'completed_with_errors':
      return (
        <Badge variant="outline" className="border-amber-500/40 text-amber-500 bg-amber-500/10 gap-1.5 font-medium">
          <AlertTriangle className="h-3 w-3" />
          Есть ошибки
        </Badge>
      )
    case 'failed':
      return (
        <Badge variant="outline" className="border-rose-500/40 text-rose-500 bg-rose-500/10 gap-1.5 font-medium">
          <XCircle className="h-3 w-3" />
          Сбой
        </Badge>
      )
  }
}
```

Create `frontend/src/features/data-ingestion/ui/import-upload-dropzone.tsx`:
```tsx
'use client'

import React, { useState, useRef } from 'react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { UploadCloud, FileSpreadsheet, AlertCircle, Loader2 } from 'lucide-react'
import type { DatasetType } from '../api/import-gateway'

interface ImportUploadDropzoneProps {
  onUpload: (file: File, datasetType: DatasetType) => Promise<void>
  isUploading: boolean
}

export function ImportUploadDropzone({ onUpload, isUploading }: ImportUploadDropzoneProps) {
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
          Загрузите CSV или JSON файл для потокового импорта в хранилище и автоматической проекции в Star Schema аналитики.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label htmlFor="dataset-type-select" className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
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
                  <p className="font-semibold text-foreground text-sm">{selectedFile.name}</p>
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
```

Create `frontend/src/features/data-ingestion/ui/import-batch-list.tsx`:
```tsx
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
}

export function ImportBatchList({
  batches,
  onSelectBatch,
  onRetryBatch,
  retryingBatchId,
}: ImportBatchListProps) {
  if (batches.length === 0) {
    return (
      <div className="text-center py-12 border border-dashed rounded-xl border-border/80">
        <p className="text-muted-foreground text-sm">Нет загруженных наборов данных.</p>
        <p className="text-xs text-muted-foreground/70 mt-1">Загрузите первый CSV-файл выше для начала обработки.</p>
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
            const canRetry = batch.status === 'failed' || batch.status === 'completed_with_errors'
            const percent = batch.progress_percentage ?? (batch.total_rows > 0 ? Math.round((batch.processed_rows / batch.total_rows) * 100) : 0)

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
                    <span className="truncate max-w-[240px] sm:max-w-xs">{batch.original_filename}</span>
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
                        {batch.successful_rows} / {batch.total_rows || batch.processed_rows}
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
                        <RefreshCw className={`h-3.5 w-3.5 mr-1 ${isRetrying ? 'animate-spin' : ''}`} />
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test src/features/data-ingestion/ui/import-components.test.tsx`
Expected: PASS with 7 passing tests.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/data-ingestion/ui/
git commit -m "feat(data-ingestion): add core UI components (upload, status badge, batch list) with tests"
```

---

### Task 3: Batch Details & Validation Failures Slide-Over Sheet

**Files:**
- Create: `frontend/src/features/data-ingestion/ui/import-batch-detail-sheet.tsx`
- Create: `frontend/src/features/data-ingestion/ui/import-batch-detail-sheet.test.tsx`

**Interfaces:**
- Consumes:
  - `importGateway` from `../api/import-gateway`
  - `ImportBatchSummary`, `ImportFailureItem` from `../api/import-gateway`
  - shadcn primitives: `Sheet`, `SheetContent`, `SheetHeader`, `SheetTitle`, `SheetDescription`, `Table`, `Badge`, `Button`
- Produces:
  - `ImportBatchDetailSheet({ batch: ImportBatchSummary | null, isOpen: boolean, onClose: () => void, userId: string, workspaceId: string, onRetryBatch: (batchId: string) => Promise<void>, isRetrying: boolean })`

- [ ] **Step 1: Write failing tests in `frontend/src/features/data-ingestion/ui/import-batch-detail-sheet.test.tsx`**

```typescript
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
      expect(importGateway.getFailures).toHaveBeenCalledWith('batch-detail-1', 'user-1', 'ws-1', {
        page: 1,
        perPage: 50,
      })
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
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test src/features/data-ingestion/ui/import-batch-detail-sheet.test.tsx`
Expected: FAIL with "Cannot find module './import-batch-detail-sheet'".

- [ ] **Step 3: Implement `frontend/src/features/data-ingestion/ui/import-batch-detail-sheet.tsx`**

```tsx
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
import { RefreshCw, CheckCircle2, AlertTriangle, Layers, Clock, AlertCircle, Loader2 } from 'lucide-react'
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

  const loadFailures = useCallback(async (batchId: string, pageNum: number) => {
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
      setFailureError(err instanceof Error ? err.message : 'Не удалось загрузить список ошибок')
    } finally {
      setIsLoadingFailures(false)
    }
  }, [userId, workspaceId])

  useEffect(() => {
    if (batch && isOpen && batch.failed_rows > 0) {
      loadFailures(batch.id, 1)
    } else {
      setFailures([])
      setPage(1)
      setTotalPages(1)
    }
  }, [batch, isOpen, loadFailures])

  if (!batch) return null

  const canRetry = batch.status === 'failed' || batch.status === 'completed_with_errors'

  return (
    <Sheet open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <SheetContent side="right" className="w-full sm:max-w-2xl overflow-y-auto p-6 space-y-6">
        <SheetHeader className="space-y-1">
          <div className="flex items-center justify-between gap-2 pr-6">
            <SheetTitle className="text-xl font-bold truncate">
              {batch.original_filename}
            </SheetTitle>
            <ImportStatusBadge status={batch.status} />
          </div>
          <SheetDescription className="text-xs text-muted-foreground">
            ID: {batch.id} • Набор: {batch.dataset_type === 'sales' ? 'Продажи' : 'Остатки на складе'}
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
              <p className="text-lg font-bold mt-1 text-rose-400">
                {batch.failed_rows}
              </p>
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
                <RefreshCw className={`h-3 w-3 mr-1.5 ${isRetrying ? 'animate-spin' : ''}`} />
                Повторить импорт
              </Button>
            )}
          </div>

          {isLoadingFailures ? (
            <div className="flex justify-center py-8 text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin" />
            </div>
          ) : failureError ? (
            <p className="text-xs text-destructive p-3 rounded bg-destructive/10">{failureError}</p>
          ) : failures.length === 0 ? (
            <div className="py-6 text-center text-xs text-muted-foreground border border-dashed rounded-lg">
              {batch.failed_rows === 0 ? 'Все строки успешно прошли валидацию и спроецированы.' : 'Нет записей об ошибках.'}
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
                      <TableCell className="font-mono text-muted-foreground truncate max-w-[120px]" title={f.value ?? ''}>
                        {f.value || '—'}
                      </TableCell>
                      <TableCell className="text-rose-400">
                        {f.error_message}
                      </TableCell>
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test src/features/data-ingestion/ui/import-batch-detail-sheet.test.tsx`
Expected: PASS with 2 passing tests.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/features/data-ingestion/ui/import-batch-detail-sheet.tsx frontend/src/features/data-ingestion/ui/import-batch-detail-sheet.test.tsx
git commit -m "feat(data-ingestion): add batch detail and failure inspector slide-over sheet with tests"
```

---

### Task 4: Container View with Auto-polling, App Router Page & Navigation Integration

**Files:**
- Create: `frontend/src/features/data-ingestion/ui/data-ingestion-view.tsx`
- Create: `frontend/app/(dashboard)/imports/page.tsx`
- Modify: `frontend/src/shared/ui/layout/sidebar.tsx:17-25`
- Create: `frontend/src/features/data-ingestion/ui/data-ingestion-flow.test.tsx`

**Interfaces:**
- Consumes:
  - `importGateway` from `../api/import-gateway`
  - `ImportUploadDropzone`, `ImportBatchList`, `ImportBatchDetailSheet` from `./`
  - `getSession` from `../../auth/model/session`
  - `workspaceGateway` from `../../workspace/api/workspace-gateway`
- Produces:
  - `DataIngestionView({ userId: string, workspaceId: string })`
  - Next.js Page: `/imports`
  - Updated `Sidebar` with `/imports` route entry

- [ ] **Step 1: Write failing full flow tests in `frontend/src/features/data-ingestion/ui/data-ingestion-flow.test.tsx`**

```typescript
import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
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
    vi.useFakeTimers()
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
      expect(importGateway.uploadBatch).toHaveBeenCalledWith('user-1', 'ws-1', file, 'sales')
    })
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test src/features/data-ingestion/ui/data-ingestion-flow.test.tsx`
Expected: FAIL with "Cannot find module './data-ingestion-view'".

- [ ] **Step 3: Implement `DataIngestionView`, Page, and Sidebar navigation**

Create `frontend/src/features/data-ingestion/ui/data-ingestion-view.tsx`:
```tsx
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

  const loadBatches = useCallback(async (silent = false) => {
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
  }, [userId, workspaceId, statusFilter, datasetFilter])

  // Initial load and filter change
  useEffect(() => {
    loadBatches()
  }, [loadBatches])

  // Polling for active imports
  useEffect(() => {
    const hasActiveBatches = batches.some((b) =>
      ['pending', 'validating', 'processing'].includes(b.status)
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
            <p className="text-xs text-muted-foreground">Пакеты импорта данных, их статус обработки и журнал ошибок валидации.</p>
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
```

Create `frontend/app/(dashboard)/imports/page.tsx`:
```tsx
import React, { Suspense } from 'react'
import { redirect } from 'next/navigation'
import { getSession } from '../../../src/features/auth/model/session'
import {
  workspaceGateway,
  type CurrentWorkspace,
} from '../../../src/features/workspace/api/workspace-gateway'
import { DataIngestionView } from '../../../src/features/data-ingestion/ui/data-ingestion-view'
import { Skeleton } from '@/components/ui/skeleton'

export const dynamic = 'force-dynamic'

export default async function ImportsPage() {
  const session = await getSession()
  if (!session) {
    redirect('/login')
  }

  const userId = session.userId

  let workspaceContext: CurrentWorkspace | null = null

  try {
    workspaceContext = await workspaceGateway.getCurrentWorkspace(userId)
  } catch {
    // Backend fallback
  }

  const workspaceId = workspaceContext?.workspace.id ?? 'ws-1'

  return (
    <main className="px-4 sm:px-6 lg:px-8 py-6 pb-16">
      <div className="mb-6 space-y-2">
        <span className="text-xs font-semibold text-emerald-400 tracking-wider uppercase">
          AUTOBI / DATA INGESTION
        </span>
        <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-foreground">
          Импорт данных
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
          Загрузка сырых наборов данных продаж и складских остатков, потоковая валидация, изоляция некорректных строк и автоматическая проекция в витрины аналитики.
        </p>
      </div>

      <Suspense
        fallback={
          <div className="space-y-4 py-4">
            <Skeleton className="h-48 w-full rounded-xl" />
            <Skeleton className="h-64 w-full rounded-xl" />
          </div>
        }
      >
        <DataIngestionView userId={userId} workspaceId={workspaceId} />
      </Suspense>
    </main>
  )
}
```

Modify `frontend/src/shared/ui/layout/sidebar.tsx`:
Replace `NAVIGATION_ITEMS`:
```typescript
import { BarChart3, Package, Users, Bell, Settings, LayoutDashboard, UploadCloud } from 'lucide-react'

export const NAVIGATION_ITEMS: NavigationItem[] = [
  { name: 'Sales Analytics', href: '/', icon: BarChart3 },
  { name: 'Inventory', href: '/inventory', icon: Package },
  { name: 'Dashboards', href: '/dashboards', icon: LayoutDashboard },
  { name: 'Импорт данных', href: '/imports', icon: UploadCloud },
  { name: 'Suppliers', href: '/suppliers', icon: Users, disabled: true, badge: 'Скоро' },
  { name: 'Alerts', href: '/alerts', icon: Bell, disabled: true, badge: 'Скоро' },
  { name: 'Settings', href: '/settings', icon: Settings, disabled: true, badge: 'Скоро' },
]
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test src/features/data-ingestion/ui/data-ingestion-flow.test.tsx`
Expected: PASS with 2 passing tests.

- [ ] **Step 5: Run full frontend test suite and typecheck**

Run:
```bash
npm run typecheck
npm run lint
npm test
```
Expected: All suites PASS with 0 errors.

- [ ] **Step 6: Commit**

```bash
git add frontend/app/\(dashboard\)/imports/ frontend/src/features/data-ingestion/ frontend/src/shared/ui/layout/sidebar.tsx
git commit -m "feat(data-ingestion): add DataIngestionView, App Router page and sidebar navigation"
```

---

## Verification Plan

### Automated Tests
1. **Frontend Unit & Component Tests:**
   ```bash
   cd frontend && npm test
   ```
   Ensures all existing tests (134 tests) + new Data Ingestion tests pass without regression.

2. **Frontend Typecheck & Lint:**
   ```bash
   cd frontend && npm run typecheck && npm run lint
   ```
   Validates zero TypeScript errors and adherence to ESLint / Prettier code style.

3. **Backend Tests:**
   ```bash
   cd backend && ./vendor/bin/phpunit
   ```
   Confirms backend APIs and pipeline remain 100% green (194 tests).

### Manual Verification
1. Open web browser at `http://localhost:3000/imports`.
2. Verify sidebar contains "Импорт данных" link with `UploadCloud` icon.
3. Test uploading a sample sales CSV file: observe progress bar and status change from `pending` -> `processing` -> `completed`.
4. Test uploading a CSV with invalid rows: observe status `completed_with_errors`.
5. Open the slide-over Sheet: verify error rows list displaying row number, field, value, and validation message.
6. Click "Повторить импорт" (Retry): observe status resetting to `pending` and re-processing.
