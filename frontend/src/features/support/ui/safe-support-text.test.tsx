import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { SafeSupportText } from './safe-support-text'

describe('SafeSupportText', () => {
  it('renders untrusted HTML and image markdown as inert text', () => {
    const { container } = render(
      <SafeSupportText
        text={'<script>alert(1)</script>\n\n![pixel](https://evil.example/pixel)'}
      />,
    )

    expect(screen.getByText('<script>alert(1)</script>')).toBeInTheDocument()
    expect(screen.getByText('![pixel](https://evil.example/pixel)')).toBeInTheDocument()
    expect(container.querySelector('script')).toBeNull()
    expect(container.querySelector('img')).toBeNull()
  })

  it('renders a safe markdown subset without injecting HTML', () => {
    render(
      <SafeSupportText
        text={'## Шаги\n- Откройте импорт\n- Выберите CSV\n\n```\nsku,quantity\n```'}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Шаги', level: 2 })).toBeInTheDocument()
    expect(screen.getByRole('list')).toBeInTheDocument()
    expect(screen.getByText('sku,quantity')).toBeInTheDocument()
  })
})
