import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Badge } from './badge'

const meta = {
  title: 'UI/Badge',
  component: Badge,
  parameters: {
    docs: {
      description: {
        component:
          'Короткий статус. Импорт: `@/components/ui/badge`. Пример: `<Badge variant="secondary">Новый</Badge>`.',
      },
    },
  },
  args: { children: 'Активен' },
} satisfies Meta<typeof Badge>
export default meta
type Story = StoryObj<typeof meta>
export const Default: Story = {}
export const Variants: Story = {
  render: () => (
    <div className="flex flex-wrap gap-2">
      {(['default', 'secondary', 'outline', 'destructive'] as const).map((variant) => (
        <Badge key={variant} variant={variant}>
          {variant}
        </Badge>
      ))}
    </div>
  ),
}

export const Dark: Story = { ...Default, globals: { theme: 'dark' } }
