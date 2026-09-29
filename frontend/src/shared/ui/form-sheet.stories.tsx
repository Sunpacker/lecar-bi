import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { expect, userEvent, within } from 'storybook/test'
import { Button } from '@/components/ui/button'
import { FormSheet } from './form-sheet'
import { TextField } from './text-field'

type Values = { name: string }
function ExampleFormSheet() {
  const [open, setOpen] = useState(false)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState('')
  const form = useForm<Values>({ defaultValues: { name: '' } })
  function submit({ name }: Values) {
    if (name === 'Ошибка') {
      setError('Не удалось сохранить')
      return
    }
    setError('')
    setSaved(name)
    setOpen(false)
  }
  return (
    <>
      <Button type="button" onClick={() => setOpen(true)}>
        Открыть форму
      </Button>
      {saved && <p role="status">Сохранено: {saved}</p>}
      <FormSheet
        open={open}
        onOpenChange={setOpen}
        title="Новый отчёт"
        description="Введите название отчёта"
        submitError={error}
        onSubmit={form.handleSubmit(submit)}
      >
        <Controller
          name="name"
          control={form.control}
          rules={{ required: 'Введите название' }}
          render={({ field, fieldState }) => (
            <TextField label="Название" error={fieldState.error?.message} {...field} />
          )}
        />
      </FormSheet>
    </>
  )
}
const meta = {
  title: 'Compositions/Forms/FormSheet',
  component: ExampleFormSheet,
  parameters: {
    docs: {
      description: {
        component:
          'Управляемая форма в панели; закрытие после submit определяет вызывающий код. Импорт: `@/src/shared/ui`. Пример: `<FormSheet open={open} onOpenChange={setOpen} title="Отчёт" onSubmit={handleSubmit}>...</FormSheet>`.',
      },
    },
  },
} satisfies Meta<typeof ExampleFormSheet>
export default meta
type Story = StoryObj<typeof meta>
export const ValidationErrorAndSuccess: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await userEvent.click(canvas.getByRole('button', { name: 'Открыть форму' }))
    let dialog = await within(document.body).findByRole('dialog')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Сохранить' }))
    await expect(within(dialog).getByRole('alert')).toHaveTextContent('Введите название')
    const input = within(dialog).getByRole('textbox', { name: 'Название' })
    await userEvent.type(input, 'Ошибка')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Сохранить' }))
    await expect(within(dialog).getByRole('alert')).toHaveTextContent(
      'Не удалось сохранить',
    )
    await userEvent.clear(input)
    await userEvent.type(input, 'Отчёт')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Сохранить' }))
    await expect(canvas.getByRole('status')).toHaveTextContent('Отчёт')
  },
}

export const Dark: Story = { ...ValidationErrorAndSuccess, globals: { theme: 'dark' } }
