import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Skeleton } from './skeleton'

const meta = {
  title: 'UI/Skeleton',
  component: Skeleton,
  parameters: {
    docs: {
      description: {
        component:
          'Заглушка на время загрузки. Импорт: `@/components/ui/skeleton`. Пример: `<Skeleton className="h-8 w-40" />`.',
      },
    },
  },
} satisfies Meta<typeof Skeleton>
export default meta
type Story = StoryObj<typeof meta>
export const CardLoading: Story = {
  render: () => (
    <div role="status" aria-label="Загрузка карточки" className="w-64 space-y-3">
      <Skeleton className="h-5 w-24" />
      <Skeleton className="h-9 w-40" />
      <Skeleton className="h-3 w-52" />
    </div>
  ),
}

export const Dark: Story = { ...CardLoading, globals: { theme: 'dark' } }
