import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { useState } from 'react'
import { expect, userEvent, within } from 'storybook/test'
import { DataTable, type DataTableColumn, type DataTableSort } from './data-table'

type Row = { id: number; product: string; amount: number }
const pages: Row[][] = [
  [
    { id: 1, product: 'Фильтр', amount: 1200 },
    { id: 2, product: 'Масло', amount: 900 },
  ],
  [{ id: 3, product: 'Свеча', amount: 600 }],
]
const columns: DataTableColumn<Row>[] = [
  { key: 'product', label: 'Товар', sortable: true, render: (row) => row.product },
  {
    key: 'amount',
    label: 'Сумма',
    align: 'right',
    sortable: true,
    render: (row) => `${row.amount} ₽`,
  },
]
function ExampleDataTable({ empty = false }: { empty?: boolean }) {
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState<DataTableSort>({ key: 'product', direction: 'desc' })
  return (
    <DataTable
      rows={empty ? [] : pages[page - 1]}
      columns={columns}
      getRowKey={(row) => row.id}
      sort={sort}
      onSortChange={setSort}
      pagination={{ page, pageSize: 2, total: empty ? 0 : 3, totalPages: empty ? 0 : 2 }}
      onPageChange={setPage}
    />
  )
}
const meta = {
  title: 'Compositions/BI/DataTable',
  component: ExampleDataTable,
  parameters: {
    docs: {
      description: {
        component:
          'Отображает только переданную страницу; сортировку и загрузку выполняет вызывающий код. Импорт: `@/src/shared/ui`. Пример: `<DataTable rows={rows} columns={columns} getRowKey={row => row.id} />`.',
      },
    },
  },
} satisfies Meta<typeof ExampleDataTable>
export default meta
type Story = StoryObj<typeof meta>
export const SortingAndPagination: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await userEvent.click(canvas.getByRole('button', { name: /Товар/ }))
    await expect(canvas.getByRole('columnheader', { name: /Товар/ })).toHaveAttribute(
      'aria-sort',
      'ascending',
    )
    await userEvent.click(canvas.getByRole('button', { name: /Вперед/ }))
    await expect(canvas.getByText('Свеча')).toBeInTheDocument()
    await expect(canvas.getByRole('button', { name: /Вперед/ })).toBeDisabled()
  },
}
export const Empty: Story = { args: { empty: true } }

export const Dark: Story = { ...SortingAndPagination, globals: { theme: 'dark' } }
