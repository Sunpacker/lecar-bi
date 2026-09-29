import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from './table'

const meta = {
  title: 'UI/Table',
  component: Table,
  parameters: {
    docs: {
      description: {
        component:
          'Таблица данных с горизонтальной прокруткой. Импорт: `@/components/ui/table`. Пример: `<Table><TableHeader><TableRow><TableHead>Имя</TableHead></TableRow></TableHeader><TableBody><TableRow><TableCell>Товар</TableCell></TableRow></TableBody></Table>`.',
      },
    },
  },
} satisfies Meta<typeof Table>
export default meta
type Story = StoryObj<typeof meta>
export const RowsAndFooter: Story = {
  render: () => (
    <div className="max-w-sm">
      <Table>
        <TableCaption>Продажи за период</TableCaption>
        <TableHeader>
          <TableRow>
            <TableHead>Товар</TableHead>
            <TableHead className="text-right">Сумма</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow>
            <TableCell>Фильтр</TableCell>
            <TableCell className="text-right">1 250 ₽</TableCell>
          </TableRow>
        </TableBody>
        <TableFooter>
          <TableRow>
            <TableCell>Итого</TableCell>
            <TableCell className="text-right">1 250 ₽</TableCell>
          </TableRow>
        </TableFooter>
      </Table>
    </div>
  ),
}
export const Empty: Story = {
  render: () => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Товар</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow>
          <TableCell className="text-center">Нет результатов</TableCell>
        </TableRow>
      </TableBody>
    </Table>
  ),
}
export const Scroll: Story = {
  render: () => (
    <div className="w-64">
      <Table>
        <TableHeader>
          <TableRow>
            {Array.from({ length: 8 }, (_, i) => (
              <TableHead key={i} className="min-w-32">
                Колонка {i + 1}
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow>
            {Array.from({ length: 8 }, (_, i) => (
              <TableCell key={i}>Значение {i + 1}</TableCell>
            ))}
          </TableRow>
        </TableBody>
      </Table>
    </div>
  ),
}

export const Dark: Story = { ...RowsAndFooter, globals: { theme: 'dark' } }
