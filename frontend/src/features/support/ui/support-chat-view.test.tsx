import React from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type {
  SupportConversation,
  SupportGeneration,
  SupportMessage,
} from '../api/support-gateway'
import { supportGateway } from '../api/support-gateway'
import { SupportChatView } from './support-chat-view'

vi.mock('../api/support-gateway', () => ({
  supportGateway: {
    listConversations: vi.fn(),
    createConversation: vi.fn(),
    deleteConversation: vi.fn(),
    listMessages: vi.fn(),
    sendMessage: vi.fn(),
    getGeneration: vi.fn(),
    retryGeneration: vi.fn(),
    submitFeedback: vi.fn(),
    streamGeneration: vi.fn(),
  },
  SupportApiError: class SupportApiError extends Error {},
}))

const NOW = '2026-09-24T10:00:00Z'
const conversation: SupportConversation = {
  id: '00000000-0000-4000-8000-000000000010',
  workspace_id: 'ws-1',
  title: 'Импорт',
  created_at: NOW,
  updated_at: NOW,
}
const runningMessages: SupportMessage[] = [
  {
    id: '00000000-0000-4000-8000-000000000011',
    conversation_id: conversation.id,
    role: 'user',
    content: 'Как загрузить CSV?',
    position: 1,
    citations: [],
    created_at: NOW,
  },
  {
    id: '00000000-0000-4000-8000-000000000012',
    conversation_id: conversation.id,
    role: 'assistant',
    content: '',
    position: 2,
    generation_id: '00000000-0000-4000-8000-000000000013',
    generation_status: 'running',
    outcome: null,
    citations: [],
    created_at: NOW,
  },
]
const answeredMessages: SupportMessage[] = [
  runningMessages[0],
  {
    ...runningMessages[1],
    content: 'Откройте раздел «Импорт данных».',
    generation_status: 'completed',
    outcome: 'answered',
    citations: [
      {
        document_id: 'imports',
        revision: 'r1',
        chunk_id: '00000000-0000-4000-8000-000000000014',
        title: 'Импорт данных',
        url: '/support/imports',
        anchor: 'csv',
        available: true,
      },
    ],
  },
]
const completedGeneration: SupportGeneration = {
  id: '00000000-0000-4000-8000-000000000013',
  conversation_id: conversation.id,
  user_message_id: runningMessages[0].id,
  assistant_message_id: runningMessages[1].id,
  attempt: 1,
  status: 'completed',
  outcome: 'answered',
  sequence: 2,
  text: answeredMessages[1].content,
  retryable: false,
  citations: answeredMessages[1].citations,
  created_at: NOW,
  updated_at: NOW,
}

describe('SupportChatView flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(supportGateway.submitFeedback).mockResolvedValue({
      message_id: runningMessages[1].id,
      rating: 'helpful',
      updated_at: NOW,
    })
  })

  it('reconnects without a second send, restores history, citations and feedback', async () => {
    vi.mocked(supportGateway.listConversations).mockResolvedValue({
      items: [conversation],
      next_cursor: null,
    })
    vi.mocked(supportGateway.listMessages)
      .mockResolvedValueOnce({ items: [], next_cursor: null })
      .mockResolvedValueOnce({ items: runningMessages, next_cursor: null })
      .mockResolvedValue({ items: answeredMessages, next_cursor: null })
    vi.mocked(supportGateway.sendMessage).mockResolvedValue({
      message_id: runningMessages[0].id,
      assistant_message_id: runningMessages[1].id,
      generation_id: completedGeneration.id,
    })
    vi.mocked(supportGateway.streamGeneration)
      .mockImplementationOnce(async (_workspaceId, _generationId, onEvent) => {
        onEvent({
          type: 'snapshot',
          data: {
            generation_id: completedGeneration.id,
            sequence: 1,
            status: 'running',
            text: 'Откройте раздел',
          },
        })
        throw new Error('connection lost')
      })
      .mockImplementationOnce(async (_workspaceId, _generationId, onEvent) => {
        onEvent({
          type: 'completed',
          data: {
            generation_id: completedGeneration.id,
            sequence: 2,
            status: 'completed',
            outcome: 'answered',
            text: completedGeneration.text,
            citations: completedGeneration.citations,
          },
        })
      })
    vi.mocked(supportGateway.getGeneration).mockResolvedValue({
      generation: completedGeneration,
    })

    render(<SupportChatView workspaceId="ws-1" />)
    await screen.findByText('История пока пуста.')

    fireEvent.change(screen.getByLabelText('Вопрос поддержке'), {
      target: { value: 'Как загрузить CSV?' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Отправить' }))

    await screen.findByText('Откройте раздел «Импорт данных».', {}, { timeout: 3000 })
    expect(supportGateway.sendMessage).toHaveBeenCalledTimes(1)
    expect(supportGateway.streamGeneration).toHaveBeenCalledTimes(2)
    expect(vi.mocked(supportGateway.streamGeneration).mock.calls[1][4]).toBe(1)
    expect(screen.getByRole('link', { name: 'Импорт данных' }).getAttribute('href')).toBe(
      '/support/imports#csv',
    )

    fireEvent.click(screen.getByRole('button', { name: 'Ответ помог' }))
    await waitFor(() =>
      expect(supportGateway.submitFeedback).toHaveBeenCalledWith(
        'ws-1',
        runningMessages[1].id,
        'helpful',
      ),
    )
  }, 5000)

  it('hides the previous workspace history immediately during a switch', async () => {
    let resolveSecondWorkspace!: (value: {
      items: SupportConversation[]
      next_cursor: string | null
    }) => void
    vi.mocked(supportGateway.listConversations)
      .mockResolvedValueOnce({ items: [conversation], next_cursor: null })
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecondWorkspace = resolve
        }),
      )
    vi.mocked(supportGateway.listMessages).mockResolvedValue({
      items: answeredMessages,
      next_cursor: null,
    })

    const view = render(<SupportChatView workspaceId="ws-1" />)
    await screen.findByText('Откройте раздел «Импорт данных».')

    view.rerender(<SupportChatView workspaceId="ws-2" />)
    expect(screen.queryByText('Откройте раздел «Импорт данных».')).toBeNull()
    expect(screen.getByText('Загрузка истории…')).toBeDefined()

    resolveSecondWorkspace({ items: [], next_cursor: null })
    await screen.findByText('История пока пуста.')
  })
})
