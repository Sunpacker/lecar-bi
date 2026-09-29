import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Bar, BarChart, Line, LineChart, XAxis, YAxis } from 'recharts'
import { ChartCard } from './chart-card'
import {
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'

const data = [
  { month: 'Янв', sales: 120, orders: 80 },
  { month: 'Фев', sales: 180, orders: 130 },
  { month: 'Мар', sales: 140, orders: 100 },
]
const config = {
  sales: { label: 'Продажи', color: 'var(--chart-1)' },
  orders: { label: 'Заказы', color: 'var(--chart-2)' },
}
const meta = {
  title: 'Compositions/BI/ChartCard',
  component: ChartCard,
  args: { title: 'Динамика', config, children: null },
  parameters: {
    docs: {
      description: {
        component:
          'Карточка с ChartContainer, заголовком и действиями. Импорт: `@/src/shared/ui`. Пример: `<ChartCard title="Динамика" config={config}><LineChart data={data}>...</LineChart></ChartCard>`.',
      },
    },
  },
} satisfies Meta<typeof ChartCard>
export default meta
type Story = StoryObj<typeof meta>
export const LineSeries: Story = {
  render: (args) => (
    <ChartCard {...args}>
      <LineChart data={data}>
        <XAxis dataKey="month" />
        <YAxis />
        <ChartTooltip content={<ChartTooltipContent />} />
        <ChartLegend content={<ChartLegendContent />} />
        <Line dataKey="sales" stroke="var(--color-sales)" />
        <Line dataKey="orders" stroke="var(--color-orders)" />
      </LineChart>
    </ChartCard>
  ),
}
export const BarSeries: Story = {
  render: (args) => (
    <ChartCard {...args}>
      <BarChart data={data}>
        <XAxis dataKey="month" />
        <YAxis />
        <ChartTooltip content={<ChartTooltipContent />} />
        <ChartLegend content={<ChartLegendContent />} />
        <Bar dataKey="sales" fill="var(--color-sales)" />
        <Bar dataKey="orders" fill="var(--color-orders)" />
      </BarChart>
    </ChartCard>
  ),
}

export const Dark: Story = { ...LineSeries, globals: { theme: 'dark' } }
