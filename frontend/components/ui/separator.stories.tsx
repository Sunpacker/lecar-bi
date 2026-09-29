import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Separator } from './separator'

const meta = {
  title: 'UI/Separator',
  component: Separator,
  parameters: {
    docs: {
      description: {
        component:
          'Разделитель содержимого. Импорт: `@/components/ui/separator`. Пример: `<Separator orientation="horizontal" />`.',
      },
    },
  },
} satisfies Meta<typeof Separator>
export default meta
type Story = StoryObj<typeof meta>
export const Horizontal: Story = {
  render: () => (
    <div className="max-w-xs space-y-3">
      <p>Сводка</p>
      <Separator />
      <p>Детали</p>
    </div>
  ),
}
export const Vertical: Story = {
  render: () => (
    <div className="flex h-8 items-center gap-3">
      <span>Продажи</span>
      <Separator orientation="vertical" />
      <span>Склад</span>
    </div>
  ),
}

export const Dark: Story = { ...Horizontal, globals: { theme: 'dark' } }
