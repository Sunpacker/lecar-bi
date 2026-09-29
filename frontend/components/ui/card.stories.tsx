import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Button } from './button'
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from './card'

const meta = {
  title: 'UI/Card',
  component: Card,
  parameters: {
    docs: {
      description: {
        component:
          'Контейнер со структурированным содержимым. Импорт: `@/components/ui/card`. Пример: `<Card><CardHeader><CardTitle>Заголовок</CardTitle></CardHeader><CardContent>Данные</CardContent></Card>`.',
      },
    },
  },
} satisfies Meta<typeof Card>
export default meta
type Story = StoryObj<typeof meta>
export const Complete: Story = {
  render: () => (
    <Card className="max-w-sm">
      <CardHeader>
        <CardTitle>Показатель</CardTitle>
        <CardDescription>За текущий период</CardDescription>
        <CardAction>
          <Button variant="outline" size="sm">
            Подробнее
          </Button>
        </CardAction>
      </CardHeader>
      <CardContent>125 заказов</CardContent>
      <CardFooter>Обновлено сегодня</CardFooter>
    </Card>
  ),
}
export const Sizes: Story = {
  render: () => (
    <div className="flex flex-wrap gap-4">
      <Card size="sm">
        <CardHeader>
          <CardTitle>Компактная</CardTitle>
        </CardHeader>
        <CardContent>Малый размер</CardContent>
      </Card>
      <Card size="default">
        <CardHeader>
          <CardTitle>Обычная</CardTitle>
        </CardHeader>
        <CardContent>Стандартный размер</CardContent>
      </Card>
    </div>
  ),
}

export const Dark: Story = { ...Complete, globals: { theme: 'dark' } }
