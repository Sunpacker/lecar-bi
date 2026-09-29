import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Progress, ProgressLabel, ProgressValue } from './progress'

const meta = {
  title: 'UI/Progress',
  component: Progress,
  args: { value: 60 },
  parameters: {
    docs: {
      description: {
        component:
          'Доля выполненной работы. Импорт: `@/components/ui/progress`. Пример: `<Progress value={60}><ProgressLabel>Импорт</ProgressLabel><ProgressValue /></Progress>`.',
      },
    },
  },
} satisfies Meta<typeof Progress>
export default meta
type Story = StoryObj<typeof meta>
export const Partial: Story = {
  args: {},
  render: () => (
    <Progress value={60} className="max-w-sm">
      <ProgressLabel>Загрузка данных</ProgressLabel>
      <ProgressValue />
    </Progress>
  ),
}
export const Complete: Story = {
  args: {},
  render: () => (
    <Progress value={100} className="max-w-sm">
      <ProgressLabel>Готово</ProgressLabel>
      <ProgressValue />
    </Progress>
  ),
}

export const Dark: Story = { ...Partial, globals: { theme: 'dark' } }
