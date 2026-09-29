import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { useState } from 'react'
import { expect, userEvent, within } from 'storybook/test'
import { FilterBar } from './filter-bar'
import { TextField } from './text-field'

function ExampleFilterBar({ disabled = false }: { disabled?: boolean }) {
  const [query, setQuery] = useState('Фильтр')
  return (
    <FilterBar disabled={disabled} onReset={() => setQuery('')}>
      <TextField
        label="Поиск"
        value={query}
        onChange={(event) => setQuery(event.target.value)}
      />
    </FilterBar>
  )
}
const meta = {
  title: 'Compositions/BI/FilterBar',
  component: ExampleFilterBar,
  parameters: {
    docs: {
      description: {
        component:
          'Поля фильтров через children и область действий. Импорт: `@/src/shared/ui`. Пример: `<FilterBar onReset={reset}><TextField label="Поиск" /></FilterBar>`.',
      },
    },
  },
} satisfies Meta<typeof ExampleFilterBar>
export default meta
type Story = StoryObj<typeof meta>
export const Reset: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await userEvent.click(canvas.getByRole('button', { name: 'Сбросить' }))
    await expect(canvas.getByRole('textbox', { name: 'Поиск' })).toHaveValue('')
  },
}
export const Disabled: Story = {
  args: { disabled: true },
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await expect(canvas.getByRole('textbox', { name: 'Поиск' })).toBeDisabled()
    await expect(canvas.getByRole('button', { name: 'Сбросить' })).toBeDisabled()
  },
}

export const Dark: Story = { ...Reset, globals: { theme: 'dark' } }
