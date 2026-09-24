import React from 'react'

type SupportBlock =
  | { type: 'heading'; content: string; level: number }
  | { type: 'list'; items: string[] }
  | { type: 'code'; content: string }
  | { type: 'paragraph'; content: string }

export function SafeSupportText({ text }: { text: string }) {
  return (
    <div className="space-y-2 break-words text-sm leading-6">
      {parseBlocks(text).map((block, index) => renderBlock(block, index))}
    </div>
  )
}

function parseBlocks(text: string): SupportBlock[] {
  const lines = text.replaceAll('\r\n', '\n').split('\n')
  const blocks: SupportBlock[] = []
  let paragraph: string[] = []
  let list: string[] = []
  let code: string[] | undefined

  function flushParagraph() {
    if (paragraph.length === 0) return
    blocks.push({ type: 'paragraph', content: paragraph.join('\n') })
    paragraph = []
  }

  function flushList() {
    if (list.length === 0) return
    blocks.push({ type: 'list', items: list })
    list = []
  }

  for (const line of lines) {
    if (line.trimStart().startsWith('```')) {
      flushParagraph()
      flushList()
      if (code) {
        blocks.push({ type: 'code', content: code.join('\n') })
        code = undefined
      } else {
        code = []
      }
      continue
    }
    if (code) {
      code.push(line)
      continue
    }

    const heading = /^(#{1,3})\s+(.+)$/.exec(line)
    if (heading) {
      flushParagraph()
      flushList()
      blocks.push({ type: 'heading', level: heading[1].length, content: heading[2] })
      continue
    }

    const item = /^\s*[-*]\s+(.+)$/.exec(line)
    if (item) {
      flushParagraph()
      list.push(item[1])
      continue
    }

    if (line.trim() === '') {
      flushParagraph()
      flushList()
      continue
    }

    flushList()
    paragraph.push(line)
  }

  if (code) blocks.push({ type: 'code', content: code.join('\n') })
  flushParagraph()
  flushList()

  return blocks
}

function renderBlock(block: SupportBlock, index: number) {
  const key = `${index}-${block.type}`
  if (block.type === 'heading') {
    const className = block.level === 1 ? 'font-semibold text-base' : 'font-semibold'
    return (
      <p key={key} role="heading" aria-level={block.level} className={className}>
        {block.content}
      </p>
    )
  }
  if (block.type === 'list') {
    return (
      <ul key={key} className="list-disc space-y-1 pl-5">
        {block.items.map((item, itemIndex) => (
          <li key={`${itemIndex}-${item.slice(0, 24)}`}>{item}</li>
        ))}
      </ul>
    )
  }
  if (block.type === 'code') {
    return (
      <pre key={key} className="overflow-x-auto rounded-md bg-muted px-3 py-2 text-xs">
        <code>{block.content}</code>
      </pre>
    )
  }

  return (
    <p key={key} className="whitespace-pre-wrap">
      {block.content}
    </p>
  )
}
