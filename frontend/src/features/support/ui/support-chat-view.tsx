'use client'

import React from 'react'
import {
  AlertCircle,
  Bot,
  Loader2,
  MessageCircleQuestion,
  Plus,
  RefreshCw,
  Send,
  ThumbsDown,
  ThumbsUp,
  Trash2,
  UserRound,
} from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import {
  SupportApiError,
  supportGateway,
  type SupportConversation,
  type SupportMessage,
} from '../api/support-gateway'
import type { SupportSseEvent } from '../api/sse-parser'
import { SafeSupportText } from './safe-support-text'

interface SupportChatViewProps {
  workspaceId: string
}

type ChatState = 'loading' | 'idle' | 'sending' | 'streaming' | 'reconnecting' | 'failed'

export function SupportChatView({ workspaceId }: SupportChatViewProps) {
  const [conversations, setConversations] = React.useState<SupportConversation[]>([])
  const [activeConversationId, setActiveConversationId] = React.useState<string>()
  const [messages, setMessages] = React.useState<SupportMessage[]>([])
  const [draft, setDraft] = React.useState('')
  const [state, setState] = React.useState<ChatState>('loading')
  const [error, setError] = React.useState<string>()
  const [loadedWorkspaceId, setLoadedWorkspaceId] = React.useState<string>()
  const streamController = React.useRef<AbortController | undefined>(undefined)

  const loadMessages = React.useCallback(
    async (conversationId: string) => {
      const response = await supportGateway.listMessages(workspaceId, conversationId)
      setMessages(response.items)
    },
    [workspaceId],
  )

  React.useEffect(() => {
    let cancelled = false
    streamController.current?.abort()

    supportGateway
      .listConversations(workspaceId)
      .then(async ({ items }) => {
        if (cancelled) return
        setLoadedWorkspaceId(workspaceId)
        setMessages([])
        setError(undefined)
        setConversations(items)
        const first = items[0]
        if (first) {
          setActiveConversationId(first.id)
          await loadMessages(first.id)
        }
        if (!cancelled) setState('idle')
      })
      .catch((reason: unknown) => {
        if (cancelled) return
        setLoadedWorkspaceId(workspaceId)
        setError(errorMessage(reason))
        setState('failed')
      })

    return () => {
      cancelled = true
      streamController.current?.abort()
    }
  }, [loadMessages, workspaceId])

  async function createConversation() {
    setError(undefined)
    const { conversation } = await supportGateway.createConversation(workspaceId)
    setConversations((current) => [conversation, ...current])
    setActiveConversationId(conversation.id)
    setMessages([])
    return conversation.id
  }

  async function selectConversation(conversationId: string) {
    streamController.current?.abort()
    setActiveConversationId(conversationId)
    setMessages([])
    setState('loading')
    setError(undefined)
    try {
      await loadMessages(conversationId)
      setState('idle')
    } catch (reason) {
      setError(errorMessage(reason))
      setState('failed')
    }
  }

  async function deleteConversation(conversationId: string) {
    streamController.current?.abort()
    await supportGateway.deleteConversation(workspaceId, conversationId)
    const remaining = conversations.filter((item) => item.id !== conversationId)
    setConversations(remaining)
    setMessages([])
    const next = remaining[0]
    setActiveConversationId(next?.id)
    if (next) await loadMessages(next.id)
  }

  async function sendQuestion(event: React.FormEvent) {
    event.preventDefault()
    const content = draft.trim()
    if (!content || state === 'sending' || state === 'streaming') return

    setDraft('')
    setError(undefined)
    setState('sending')

    try {
      const conversationId = activeConversationId ?? (await createConversation())
      const accepted = await supportGateway.sendMessage(
        workspaceId,
        conversationId,
        content,
      )
      await loadMessages(conversationId)
      await observeGeneration(conversationId, accepted.generation_id)
    } catch (reason) {
      setError(errorMessage(reason))
      setState('failed')
    }
  }

  async function observeGeneration(conversationId: string, generationId: string) {
    streamController.current?.abort()
    const controller = new AbortController()
    streamController.current = controller
    let attempts = 0
    let lastSequence = -1

    while (!controller.signal.aborted && attempts < 3) {
      setState(attempts === 0 ? 'streaming' : 'reconnecting')
      try {
        await supportGateway.streamGeneration(
          workspaceId,
          generationId,
          (event) => {
            if (event.data.sequence <= lastSequence) return
            lastSequence = event.data.sequence
            applyGenerationEvent(event)
          },
          controller.signal,
          lastSequence,
        )
        const { generation } = await supportGateway.getGeneration(
          workspaceId,
          generationId,
        )
        if (generation.status === 'completed' || generation.status === 'failed') {
          await loadMessages(conversationId)
          setState(generation.status === 'completed' ? 'idle' : 'failed')
          return
        }
      } catch (reason) {
        if (controller.signal.aborted) return
        if (reason instanceof SupportApiError && reason.status === 404) throw reason
      }

      attempts += 1
      await delay(500 * 2 ** attempts, controller.signal)
    }

    if (!controller.signal.aborted) {
      const { generation } = await supportGateway.getGeneration(workspaceId, generationId)
      await loadMessages(conversationId)
      setState(generation.status === 'completed' ? 'idle' : 'failed')
    }
  }

  function applyGenerationEvent(event: SupportSseEvent) {
    setMessages((current) =>
      current.map((message) => {
        if (message.generation_id !== event.data.generation_id) return message
        if (event.type === 'completed') {
          return {
            ...message,
            content: event.data.text,
            generation_status: 'completed',
            outcome: event.data.outcome,
            citations: event.data.citations,
          }
        }
        if (event.type === 'failed') {
          return { ...message, content: '', generation_status: 'failed' }
        }
        return {
          ...message,
          content: event.data.text,
          generation_status: event.data.status,
        }
      }),
    )
  }

  async function retry(message: SupportMessage) {
    if (!message.generation_id || !activeConversationId) return
    setError(undefined)
    setState('sending')

    try {
      const accepted = await supportGateway.retryGeneration(
        workspaceId,
        message.generation_id,
      )
      await loadMessages(activeConversationId)
      await observeGeneration(activeConversationId, accepted.generation_id)
    } catch (reason) {
      setError(errorMessage(reason))
      setState('failed')
    }
  }

  async function feedback(messageId: string, rating: 'helpful' | 'not_helpful') {
    try {
      await supportGateway.submitFeedback(workspaceId, messageId, rating)
    } catch (reason) {
      setError(errorMessage(reason))
    }
  }

  const workspaceReady = loadedWorkspaceId === workspaceId
  const visibleConversations = workspaceReady ? conversations : []
  const visibleMessages = workspaceReady ? messages : []
  const displayState = workspaceReady ? state : 'loading'
  const displayError = workspaceReady ? error : undefined
  const busy =
    displayState === 'loading' ||
    displayState === 'sending' ||
    displayState === 'streaming' ||
    displayState === 'reconnecting'

  return (
    <div className="grid min-h-[640px] gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
      <Card className="h-fit lg:sticky lg:top-20">
        <CardHeader className="flex-row items-center justify-between">
          <CardTitle>Диалоги</CardTitle>
          <Button
            size="icon-sm"
            variant="outline"
            aria-label="Создать диалог"
            onClick={() =>
              createConversation().catch((reason) => setError(errorMessage(reason)))
            }
          >
            <Plus />
          </Button>
        </CardHeader>
        <CardContent className="space-y-2">
          {visibleConversations.length === 0 && (
            <p className="text-sm text-muted-foreground">История пока пуста.</p>
          )}
          {visibleConversations.map((conversation) => (
            <div key={conversation.id} className="flex gap-1">
              <Button
                variant={conversation.id === activeConversationId ? 'secondary' : 'ghost'}
                className="min-w-0 flex-1 justify-start truncate"
                onClick={() => selectConversation(conversation.id)}
              >
                {conversation.title}
              </Button>
              <Button
                size="icon-sm"
                variant="ghost"
                aria-label={`Удалить диалог ${conversation.title}`}
                onClick={() => deleteConversation(conversation.id)}
              >
                <Trash2 />
              </Button>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card className="min-h-[640px]">
        <CardHeader className="border-b">
          <CardTitle className="flex items-center gap-2">
            <MessageCircleQuestion className="size-5 text-emerald-500" />
            Чат поддержки
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Вопрос должен быть самостоятельным: история не используется как evidence в
            MVP.
          </p>
          <p className="rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2 text-xs text-muted-foreground">
            Не добавляйте персональные, коммерческие или фактические BI-данные. Google
            обрабатывает вопрос и публичные фрагменты документации; во free tier данные могут
            использоваться для улучшения продуктов, резидентность в РФ не гарантируется.
          </p>
        </CardHeader>
        <CardContent className="flex min-h-[500px] flex-col gap-4">
          <div
            className="flex-1 space-y-4 overflow-y-auto pr-1"
            aria-live="polite"
            aria-busy={busy}
          >
            {displayState === 'loading' && (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" /> Загрузка истории…
              </div>
            )}
            {visibleMessages.length === 0 && displayState !== 'loading' && (
              <div className="grid min-h-64 place-items-center text-center">
                <div className="max-w-sm space-y-2">
                  <Bot className="mx-auto size-9 text-emerald-500" />
                  <p className="font-medium">
                    Спросите об интерфейсе или методике AutoBI
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Чат не читает фактические продажи, остатки и другие данные workspace.
                  </p>
                </div>
              </div>
            )}
            {visibleMessages.map((message) => (
              <article
                key={message.id}
                className={cn(
                  'max-w-[88%] rounded-xl border px-4 py-3',
                  message.role === 'user'
                    ? 'ml-auto border-emerald-500/20 bg-emerald-500/10'
                    : 'mr-auto bg-muted/40',
                )}
              >
                <header className="mb-2 flex items-center gap-2 text-xs font-medium text-muted-foreground">
                  {message.role === 'user' ? (
                    <UserRound className="size-3.5" />
                  ) : (
                    <Bot className="size-3.5" />
                  )}
                  {message.role === 'user' ? 'Вы' : 'AutoBI'}
                </header>
                {message.generation_status === 'failed' ? (
                  <div className="space-y-2">
                    <p className="flex items-center gap-2 text-sm text-destructive">
                      <AlertCircle className="size-4" /> Ответ не завершён.
                    </p>
                    <Button size="sm" variant="outline" onClick={() => retry(message)}>
                      <RefreshCw /> Повторить явно
                    </Button>
                  </div>
                ) : (
                  <SafeSupportText text={message.content || '…'} />
                )}
                {message.citations.length > 0 && (
                  <footer className="mt-3 border-t pt-2">
                    <p className="mb-1 text-xs font-medium text-muted-foreground">
                      Источники
                    </p>
                    <ul className="space-y-1 text-xs">
                      {message.citations.map((citation) => (
                        <li key={citation.chunk_id}>
                          {citation.available && citation.url ? (
                            <a
                              href={`${citation.url}${citation.anchor ? `#${citation.anchor}` : ''}`}
                              className="text-emerald-600 underline underline-offset-2"
                            >
                              {citation.title}
                            </a>
                          ) : (
                            <span className="text-muted-foreground">
                              Источник недоступен
                            </span>
                          )}
                        </li>
                      ))}
                    </ul>
                  </footer>
                )}
                {message.role === 'assistant' &&
                  message.generation_status === 'completed' && (
                    <div className="mt-2 flex gap-1" aria-label="Оценить ответ">
                      <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label="Ответ помог"
                        onClick={() => feedback(message.id, 'helpful')}
                      >
                        <ThumbsUp />
                      </Button>
                      <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label="Ответ не помог"
                        onClick={() => feedback(message.id, 'not_helpful')}
                      >
                        <ThumbsDown />
                      </Button>
                    </div>
                  )}
              </article>
            ))}
          </div>

          <div className="min-h-6 text-sm" role="status">
            {displayState === 'sending' && (
              <span className="text-muted-foreground">Вопрос принят…</span>
            )}
            {displayState === 'streaming' && (
              <span className="text-muted-foreground">Формируем ответ…</span>
            )}
            {displayState === 'reconnecting' && (
              <span className="text-amber-600">Восстанавливаем соединение…</span>
            )}
            {displayError && <span className="text-destructive">{displayError}</span>}
          </div>

          <form onSubmit={sendQuestion} className="flex gap-2 border-t pt-4">
            <label htmlFor="support-question" className="sr-only">
              Вопрос поддержке
            </label>
            <textarea
              id="support-question"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                  event.preventDefault()
                  event.currentTarget.form?.requestSubmit()
                }
              }}
              maxLength={4000}
              rows={2}
              disabled={busy}
              placeholder="Например: где посмотреть ABC/XYZ анализ?"
              className="min-h-16 flex-1 resize-none rounded-lg border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <Button
              type="submit"
              disabled={busy || draft.trim() === ''}
              className="self-end"
            >
              {busy ? <Loader2 className="animate-spin" /> : <Send />}
              Отправить
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}

function errorMessage(reason: unknown): string {
  if (reason instanceof SupportApiError) {
    if (reason.code?.includes('LIMIT') || reason.code?.includes('BUDGET')) {
      return `Лимит запросов исчерпан. Повторите через ${reason.retryAfter ?? 60} сек.`
    }
    return reason.message
  }
  return reason instanceof Error ? reason.message : 'Неизвестная ошибка чата поддержки'
}

function delay(milliseconds: number, signal: AbortSignal) {
  return new Promise<void>((resolve) => {
    const timeout = window.setTimeout(resolve, milliseconds)
    signal.addEventListener(
      'abort',
      () => {
        window.clearTimeout(timeout)
        resolve()
      },
      { once: true },
    )
  })
}
