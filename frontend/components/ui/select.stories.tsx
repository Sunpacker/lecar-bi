import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { useState } from 'react'
import { expect, userEvent, within } from 'storybook/test'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from './select'

function ExampleSelect({ disabled = false }: { disabled?: boolean }) {
  const [value, setValue] = useState('')
  return (
    <div className="max-w-xs">
      <label id="select-label">Регион</label>
      <Select
        items={[
          { value: 'samara', label: 'Самара' },
          { value: 'moscow', label: 'Москва' },
        ]}
        value={value}
        onValueChange={(next) => setValue(next ?? '')}
        disabled={disabled}
      >
        <SelectTrigger aria-labelledby="select-label">
          <SelectValue placeholder="Выберите регион" />
        </SelectTrigger>
        <SelectContent>
          <SelectGroup>
            <SelectLabel>Регионы</SelectLabel>
            <SelectItem value="samara">Самара</SelectItem>
            <SelectItem value="moscow">Москва</SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>
  )
}
const meta = {
  title: 'UI/Select',
  component: ExampleSelect,
  parameters: {
    docs: {
      description: {
        component:
          'Выбор из списка с клавиатурным управлением. Импорт: `@/components/ui/select`. Пример: `<Select><SelectTrigger><SelectValue placeholder="Выберите" /></SelectTrigger><SelectContent><SelectItem value="one">Один</SelectItem></SelectContent></Select>`.',
      },
    },
  },
} satisfies Meta<typeof ExampleSelect>
export default meta
type Story = StoryObj<typeof meta>
export const Placeholder: Story = {}
export const Disabled: Story = { args: { disabled: true } }
export const KeyboardSelection: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    const trigger = canvas.getByRole('combobox', { name: 'Регион' })
    await userEvent.click(trigger)
    await userEvent.keyboard('{ArrowDown}{Enter}')
    await expect(trigger).toHaveTextContent(/Самара|Москва/)
  },
}

export const Dark: Story = { ...Placeholder, globals: { theme: 'dark' } }
