import type { components } from '@/src/shared/api/generated/schema'

export type SupportSseEvent =
  | { type: 'snapshot'; data: components['schemas']['SupportGenerationSnapshotEvent'] }
  | { type: 'completed'; data: components['schemas']['SupportGenerationCompletedEvent'] }
  | { type: 'failed'; data: components['schemas']['SupportGenerationFailedEvent'] }

export async function readSupportEvents(
  response: Response,
  onEvent: (event: SupportSseEvent) => void,
): Promise<void> {
  if (!response.ok || !response.body)
    throw new Error('Не удалось подключиться к потоку ответа')
  if (!response.headers.get('content-type')?.includes('text/event-stream')) {
    throw new Error('Сервер вернул неподдерживаемый формат потока')
  }

  const reader = response.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ''

  while (true) {
    const { done, value } = await reader.read()
    buffer += decoder.decode(value, { stream: !done }).replaceAll('\r\n', '\n')

    let boundary = buffer.indexOf('\n\n')
    while (boundary >= 0) {
      const block = buffer.slice(0, boundary)
      buffer = buffer.slice(boundary + 2)
      parseEventBlock(block, onEvent)
      boundary = buffer.indexOf('\n\n')
    }

    if (done) break
  }
}

function parseEventBlock(block: string, onEvent: (event: SupportSseEvent) => void) {
  const lines = block.split('\n')
  const eventName = lines
    .find((line) => line.startsWith('event:'))
    ?.slice(6)
    .trim()
  const data = lines
    .filter((line) => line.startsWith('data:'))
    .map((line) => line.slice(5).trimStart())
    .join('\n')

  if (!data || !isEventName(eventName)) return
  onEvent({ type: eventName, data: JSON.parse(data) } as SupportSseEvent)
}

function isEventName(value: string | undefined): value is SupportSseEvent['type'] {
  return value === 'snapshot' || value === 'completed' || value === 'failed'
}
