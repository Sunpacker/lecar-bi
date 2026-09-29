import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Bar, BarChart, CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts'
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from './chart'

const data = [
  { month: 'Янв', revenue: 120, orders: 80 },
  { month: 'Фев', revenue: 180, orders: 110 },
  { month: 'Мар', revenue: 150, orders: 130 },
]
const config = {
  revenue: { label: 'Выручка', color: 'var(--chart-1)' },
  orders: { label: 'Заказы', color: 'var(--chart-2)' },
} satisfies ChartConfig
const meta = {
  title: 'UI/Chart',
  component: ChartContainer,
  args: { config, children: null },
  parameters: {
    docs: {
      description: {
        component:
          'Контекст оформления Recharts с tooltip и легендой. Импорт: `@/components/ui/chart`. Пример: `<ChartContainer config={config}><LineChart data={data}><Line dataKey="revenue" /></LineChart></ChartContainer>`.',
      },
    },
  },
} satisfies Meta<typeof ChartContainer>
export default meta
type Story = StoryObj<typeof meta>
export const Lines: Story = {
  args: {},
  render: () => (
    <ChartContainer config={config} className="h-64 w-full">
      <LineChart data={data}>
        <CartesianGrid vertical={false} />
        <XAxis dataKey="month" />
        <YAxis />
        <ChartTooltip content={<ChartTooltipContent />} />
        <ChartLegend content={<ChartLegendContent />} />
        <Line dataKey="revenue" stroke="var(--color-revenue)" />
        <Line dataKey="orders" stroke="var(--color-orders)" />
      </LineChart>
    </ChartContainer>
  ),
}
export const Bars: Story = {
  args: {},
  render: () => (
    <ChartContainer config={config} className="h-64 w-full">
      <BarChart data={data}>
        <XAxis dataKey="month" />
        <YAxis />
        <ChartTooltip content={<ChartTooltipContent />} />
        <ChartLegend content={<ChartLegendContent />} />
        <Bar dataKey="revenue" fill="var(--color-revenue)" />
        <Bar dataKey="orders" fill="var(--color-orders)" />
      </BarChart>
    </ChartContainer>
  ),
}

export const Dark: Story = { ...Lines, globals: { theme: 'dark' } }
