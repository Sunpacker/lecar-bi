import type { components } from '@/src/shared/api/generated/schema'
import { readSupportEvents, type SupportSseEvent } from './sse-parser'

export type SupportConversation = components['schemas']['SupportConversation']
export type SupportMessage = components['schemas']['SupportMessage']
export type SupportGeneration = components['schemas']['SupportGeneration']
export type SupportFeedbackRating =
  components['schemas']['SupportFeedbackRequest']['rating']

export class SupportApiError extends Error {
  constructor(
    message: string,
    public readonly code?: string,
    public readonly status?: number,
    public readonly retryAfter?: number,
  ) {
    super(message)
    this.name = 'SupportApiError'
  }
}

export const supportGateway = {
  async listConversations(workspaceId: string) {
    return await supportRequest<components['schemas']['SupportConversationListResponse']>(
      '/conversations',
      { workspaceId, fallback: 'Не удалось загрузить диалоги' },
    )
  },

  async createConversation(workspaceId: string, title?: string) {
    return await supportRequest<components['schemas']['SupportConversationResponse']>(
      '/conversations',
      {
        method: 'POST',
        workspaceId,
        idempotencyKey: crypto.randomUUID(),
        body: { title },
        fallback: 'Не удалось создать диалог',
      },
    )
  },

  async deleteConversation(workspaceId: string, id: string) {
    await supportRequest<void>(`/conversations/${encodeURIComponent(id)}`, {
      method: 'DELETE',
      workspaceId,
      fallback: 'Не удалось удалить диалог',
    })
  },

  async listMessages(workspaceId: string, conversationId: string) {
    return await supportRequest<components['schemas']['SupportMessageListResponse']>(
      `/conversations/${encodeURIComponent(conversationId)}/messages?per_page=100`,
      { workspaceId, fallback: 'Не удалось загрузить историю' },
    )
  },

  async sendMessage(workspaceId: string, conversationId: string, content: string) {
    return await supportRequest<
      components['schemas']['SupportGenerationAcceptedResponse']
    >(`/conversations/${encodeURIComponent(conversationId)}/messages`, {
      method: 'POST',
      workspaceId,
      idempotencyKey: crypto.randomUUID(),
      body: { client_message_id: crypto.randomUUID(), content },
      fallback: 'Не удалось отправить вопрос',
    })
  },

  async getGeneration(workspaceId: string, generationId: string) {
    return await supportRequest<components['schemas']['SupportGenerationResponse']>(
      `/generations/${encodeURIComponent(generationId)}`,
      { workspaceId, fallback: 'Не удалось получить состояние ответа' },
    )
  },

  async retryGeneration(workspaceId: string, generationId: string) {
    return await supportRequest<
      components['schemas']['SupportGenerationAcceptedResponse']
    >(`/generations/${encodeURIComponent(generationId)}/retry`, {
      method: 'POST',
      workspaceId,
      idempotencyKey: crypto.randomUUID(),
      body: { retry_request_id: crypto.randomUUID() },
      fallback: 'Не удалось повторить ответ',
    })
  },

  async submitFeedback(
    workspaceId: string,
    messageId: string,
    rating: SupportFeedbackRating,
  ) {
    return await supportRequest<components['schemas']['SupportFeedbackResponse']>(
      `/messages/${encodeURIComponent(messageId)}/feedback`,
      {
        method: 'POST',
        workspaceId,
        body: { rating },
        fallback: 'Не удалось сохранить оценку',
      },
    )
  },

  async streamGeneration(
    workspaceId: string,
    generationId: string,
    onEvent: (event: SupportSseEvent) => void,
    signal: AbortSignal,
    lastEventId: number,
  ) {
    const requestHeaders: Record<string, string> = { 'X-Workspace-Id': workspaceId }
    if (lastEventId >= 0) requestHeaders['Last-Event-ID'] = String(lastEventId)

    const response = await fetch(`/api/support/generations/${generationId}/events`, {
      headers: requestHeaders,
      cache: 'no-store',
      signal,
    })
    await readSupportEvents(response, onEvent)
  },
}

interface SupportRequestOptions {
  method?: 'GET' | 'POST' | 'DELETE'
  workspaceId: string
  idempotencyKey?: string
  body?: object
  fallback: string
}

async function supportRequest<T>(
  path: string,
  options: SupportRequestOptions,
): Promise<T> {
  const headers: Record<string, string> = { 'X-Workspace-Id': options.workspaceId }
  if (options.idempotencyKey) headers['Idempotency-Key'] = options.idempotencyKey
  if (options.body) headers['Content-Type'] = 'application/json'

  const response = await fetch(`/api/support${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
    cache: 'no-store',
  })
  if (!response.ok) await throwSupportError(response, options.fallback)
  if (response.status === 204) return undefined as T

  return (await response.json()) as T
}

async function throwSupportError(response: Response, fallback: string): Promise<never> {
  const payload = (await response.json().catch(() => undefined)) as
    { message?: string; code?: string } | undefined
  throw new SupportApiError(
    payload?.message ?? fallback,
    payload?.code,
    response.status,
    Number(response.headers.get('Retry-After')) || undefined,
  )
}
