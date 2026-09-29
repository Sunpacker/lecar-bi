import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { expect, userEvent, within } from 'storybook/test'
import { Button } from './button'
import { Input } from './input'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from './form'

type FormValues = { email: string }
function ExampleForm() {
  const form = useForm<FormValues>({ defaultValues: { email: '' } })
  const [submitted, setSubmitted] = useState('')
  return (
    <Form {...form}>
      <form
        className="max-w-sm space-y-4"
        onSubmit={form.handleSubmit(({ email }) => setSubmitted(email))}
      >
        <FormField
          control={form.control}
          name="email"
          rules={{
            required: 'Укажите email',
            pattern: { value: /^\S+@\S+\.\S+$/, message: 'Введите корректный email' },
          }}
          render={({ field }) => (
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input type="email" {...field} />
              </FormControl>
              <FormDescription>Адрес для уведомлений</FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button type="submit">Отправить</Button>
        {submitted && <p role="status">Отправлено: {submitted}</p>}
      </form>
    </Form>
  )
}
const meta = {
  title: 'UI/Form',
  component: ExampleForm,
  parameters: {
    docs: {
      description: {
        component:
          'Форма с React Hook Form. Импорт: `@/components/ui/form`. Пример: `<Form {...form}><form onSubmit={form.handleSubmit(onSubmit)}>...</form></Form>`.',
      },
    },
  },
} satisfies Meta<typeof ExampleForm>
export default meta
type Story = StoryObj<typeof meta>
export const ValidationAndSubmit: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await userEvent.click(canvas.getByRole('button', { name: 'Отправить' }))
    await expect(canvas.getByRole('alert')).toHaveTextContent('Укажите email')
    await userEvent.type(
      canvas.getByRole('textbox', { name: 'Email' }),
      'demo@example.com',
    )
    await userEvent.click(canvas.getByRole('button', { name: 'Отправить' }))
    await expect(canvas.getByRole('status')).toHaveTextContent('demo@example.com')
  },
}

export const Dark: Story = { ...ValidationAndSubmit, globals: { theme: 'dark' } }
