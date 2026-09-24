import { describe, expect, it, vi } from 'vitest'

import { readSupportEvents } from './sse-parser'

describe('readSupportEvents', () => {
  it('parses fragmented snapshots and terminal events', async () => {
    const encoder = new TextEncoder()
    const chunks = [
      'id: 1\nevent: snapshot\ndata: {"generation_id":"00000000-0000-4000-8000-000000000001",',
      '"sequence":1,"status":"running","text":"Начало"}\n\n',
      'id: 2\nevent: completed\ndata: {"generation_id":"00000000-0000-4000-8000-000000000001","sequence":2,"status":"completed","text":"Готово","outcome":"no_context","citations":[]}\n\n',
    ]
    const body = new ReadableStream<Uint8Array>({
      start(controller) {
        chunks.forEach((chunk) => controller.enqueue(encoder.encode(chunk)))
        controller.close()
      },
    })
    const response = new Response(body, {
      headers: { 'Content-Type': 'text/event-stream' },
    })
    const listener = vi.fn()

    await readSupportEvents(response, listener)

    expect(listener).toHaveBeenCalledTimes(2)
    expect(listener.mock.calls[0][0].type).toBe('snapshot')
    expect(listener.mock.calls[1][0].type).toBe('completed')
  })
})
