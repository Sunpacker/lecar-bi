import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Label } from './label'
import { Input } from './input'

const meta = {
  title: 'UI/Label',
  component: Label,
  parameters: {
    docs: {
      description: {
        component:
          'Подпись для поля ввода. Импорт: `@/components/ui/label`. Пример: `<Label htmlFor="email">Email</Label>`.',
      },
    },
  },
} satisfies Meta<typeof Label>
export default meta
type Story = StoryObj<typeof meta>
export const WithInput: Story = {
  render: () => (
    <div className="grid max-w-xs gap-2">
      <Label htmlFor="label-email">Email</Label>
      <Input id="label-email" type="email" />
    </div>
  ),
}

export const Dark: Story = { ...WithInput, globals: { theme: 'dark' } }
