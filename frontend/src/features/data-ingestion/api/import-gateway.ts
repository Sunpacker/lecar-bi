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
      throw new Error(
        error?.message ?? 'Не удалось запустить повторную обработку импорта',
      )
    }

    return data.batch
  },
}
