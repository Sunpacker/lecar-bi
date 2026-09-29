import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { expect, userEvent, waitFor, within } from 'storybook/test'
import { Button } from './button'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from './sheet'

function ExampleSheet({
  side = 'right',
}: {
  side?: 'top' | 'right' | 'bottom' | 'left'
}) {
  return (
    <Sheet>
      <SheetTrigger render={<Button variant="outline" />}>Открыть панель</SheetTrigger>
      <SheetContent side={side}>
        <SheetHeader>
          <SheetTitle>Настройка фильтров</SheetTitle>
          <SheetDescription>Измените параметры отображения.</SheetDescription>
        </SheetHeader>
        <SheetFooter>
          <SheetClose render={<Button variant="outline" />}>Закрыть</SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
const meta = {
  title: 'UI/Sheet',
  component: ExampleSheet,
  parameters: {
    docs: {
      description: {
        component:
          'Боковая или верхняя панель с возвратом фокуса. Импорт: `@/components/ui/sheet`. Пример: `<Sheet><SheetTrigger>Открыть</SheetTrigger><SheetContent><SheetTitle>Настройки</SheetTitle></SheetContent></Sheet>`.',
      },
    },
  },
} satisfies Meta<typeof ExampleSheet>
export default meta
type Story = StoryObj<typeof meta>
export const Right: Story = {
  play: async ({ canvasElement, globals }) => {
    await expect(document.documentElement.classList.contains('dark')).toBe(
      globals.theme === 'dark',
    )
    const canvas = within(canvasElement)
    const trigger = canvas.getByRole('button', { name: 'Открыть панель' })
    await userEvent.click(trigger)
    const dialog = await within(document.body).findByRole('dialog')
    await expect(dialog).toHaveTextContent('Настройка фильтров')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Закрыть' }))
    await waitFor(() => expect(trigger).toHaveFocus())
  },
}
export const Sides: Story = {
  render: () => (
    <div className="flex flex-wrap gap-2">
      {(['left', 'right', 'top', 'bottom'] as const).map((side) => (
        <ExampleSheet key={side} side={side} />
      ))}
    </div>
  ),
}

export const Dark: Story = { ...Right, globals: { theme: 'dark' } }
