import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Button } from '@/components/ui/button'
import { MetricCard } from './metric-card'

const meta = {
  title: 'Compositions/BI/MetricCard',
  component: MetricCard,
  args: { title: 'Выручка', value: '125 000 ₽', description: 'За месяц' },
  parameters: {
    docs: {
      description: {
        component:
          'Готовое отображаемое значение, описание, действие и загрузка. Импорт: `@/src/shared/ui`. Пример: `<MetricCard title="Выручка" value="125 000 ₽" />`.',
      },
    },
  },
} satisfies Meta<typeof MetricCard>
export default meta
type Story = StoryObj<typeof meta>
export const Default: Story = {}
export const WithAction: Story = {
  args: {
    action: (
      <Button variant="ghost" size="xs">
        Подробнее
      </Button>
    ),
  },
}
export const Loading: Story = { args: { loading: true } }
export const Dark: Story = { globals: { theme: 'dark' } }
