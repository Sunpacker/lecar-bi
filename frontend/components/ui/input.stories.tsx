import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Input } from './input'
import { Label } from './label'

const meta = {
  title: 'UI/Input',
  component: Input,
  parameters: {
    docs: {
      description: {
        component:
          'Однострочный ввод. Импорт: `@/components/ui/input`. Пример: `<Label htmlFor="name">Имя</Label><Input id="name" />`.',
      },
    },
  },
  args: { placeholder: 'Введите значение', 'aria-label': 'Значение' },
} satisfies Meta<typeof Input>
export default meta
type Story = StoryObj<typeof meta>
export const Default: Story = {}
export const Disabled: Story = { args: { disabled: true, value: 'Недоступно' } }
export const Invalid: Story = {
  args: { 'aria-invalid': true, defaultValue: 'Неверное значение' },
}

export const Dark: Story = { ...Default, globals: { theme: 'dark' } }
