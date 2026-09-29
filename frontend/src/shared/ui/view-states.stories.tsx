import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { useState } from 'react'
import { expect, userEvent, within } from 'storybook/test'
import { EmptyState, ErrorState, LoadingState } from './view-states'
import { Skeleton } from '@/components/ui/skeleton'

function RetryExample() {
  const [retried, setRetried] = useState(false)
  return (
    <>
      <ErrorState
        title="Ошибка загрузки"
        description="Не удалось получить данные"
        actionLabel="Повторить"
        onAction={() => setRetried(true)}
      />
      {retried && <p role="status">Повторено</p>}
    </>
  )
}
const meta = {
  title: 'Compositions/BI/ViewStates',
  component: EmptyState,
  args: { title: 'Нет данных', description: 'Измените фильтры' },
  parameters: {
    docs: {
      description: {
        component:
          'Пустое состояние, ошибка с повторным действием и доступная загрузка. Импорт: `@/src/shared/ui`. Пример: `<ErrorState title="Ошибка" actionLabel="Повторить" onAction={reload} />`.',
      },
    },
  },
} satisfies Meta<typeof EmptyState>
export default meta
type Story = StoryObj<typeof meta>
export const Empty: Story = {}
export const ErrorRetry: Story = {
  render: () => <RetryExample />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await userEvent.click(canvas.getByRole('button', { name: 'Повторить' }))
    await expect(canvas.getByRole('status')).toHaveTextContent('Повторено')
  },
}
export const Loading: Story = {
  render: () => (
    <LoadingState
      description="Загрузка отчёта"
      skeleton={
        <>
          <Skeleton className="h-8 w-32" />
          <Skeleton className="h-40 w-full" />
        </>
      }
    />
  ),
}

export const Dark: Story = { ...Empty, globals: { theme: 'dark' } }
