import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { PlusIcon } from 'lucide-react'
import { Button } from './button'

const meta = {
  title: 'UI/Button',
  component: Button,
  parameters: {
    docs: {
      description: {
        component:
          'Кнопка действия. Импорт: `@/components/ui/button`. Пример: `<Button variant="outline">Сохранить</Button>`.',
      },
    },
  },
  args: { children: 'Сохранить' },
} satisfies Meta<typeof Button>
export default meta
type Story = StoryObj<typeof meta>
export const Primary: Story = {}
export const Variants: Story = {
  render: () => (
    <div className="flex flex-wrap gap-2">
      {(['default', 'secondary', 'outline', 'ghost', 'destructive', 'link'] as const).map(
        (variant) => (
          <Button key={variant} variant={variant}>
            {variant}
          </Button>
        ),
      )}
    </div>
  ),
}
export const SizesAndIcon: Story = {
  render: () => (
    <div className="flex flex-wrap items-center gap-2">
      {(['xs', 'sm', 'default', 'lg', 'icon'] as const).map((size) => (
        <Button
          key={size}
          size={size}
          aria-label={size === 'icon' ? 'Добавить' : undefined}
        >
          <PlusIcon className="size-4" />
          {size !== 'icon' && size}
        </Button>
      ))}
    </div>
  ),
}
export const Disabled: Story = { args: { disabled: true } }

export const Dark: Story = { ...Primary, globals: { theme: 'dark' } }
