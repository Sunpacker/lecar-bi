import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { expect, userEvent, within } from 'storybook/test'
import { Button } from '@/components/ui/button'
import { SelectField } from './select-field'

const options = [
  { value: '', label: 'Все регионы' },
  { value: 'samara', label: 'Самара' },
  { value: 'moscow', label: 'Москва' },
]
function ExampleSelectField({
  disabled = false,
  error,
}: {
  disabled?: boolean
  error?: string
}) {
  const [value, setValue] = useState('')
  return (
    <div className="max-w-xs">
      <SelectField
        label="Регион"
        options={options}
        value={value}
        onValueChange={setValue}
        disabled={disabled}
        error={error}
      />
    </div>
  )
}

function ControllerExample() {
  const form = useForm<{ region: string }>({ defaultValues: { region: '' } })
  const [submitted, setSubmitted] = useState('')

  return (
    <form
      className="max-w-xs space-y-4"
      onSubmit={form.handleSubmit(({ region }) =>
        setSubmitted(options.find((option) => option.value === region)?.label ?? region),
      )}
    >
      <Controller
        name="region"
        control={form.control}
        render={({ field }) => (
          <SelectField
            label="Регион"
            options={options}
            value={field.value}
            onValueChange={field.onChange}
          />
        )}
      />
      <Button type="submit">Применить</Button>
      {submitted && <p role="status">Выбран: {submitted}</p>}
    </form>
  )
}
const meta = {
  title: 'Compositions/Forms/SelectField',
  component: ExampleSelectField,
  parameters: {
    docs: {
      description: {
        component:
          'Управляемый Select с подписью и ошибкой. Импорт: `@/src/shared/ui`. Пример: `<SelectField label="Регион" options={options} value={value} onValueChange={setValue} />`.',
      },
    },
  },
} satisfies Meta<typeof ExampleSelectField>
export default meta
type Story = StoryObj<typeof meta>
export const Selection: Story = {
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    const trigger = canvas.getByRole('combobox', { name: 'Регион' })
    await expect(trigger).toHaveTextContent('Все регионы')
    await userEvent.click(trigger)
    await userEvent.click(
      await within(document.body).findByRole('option', { name: 'Самара' }),
    )
    await expect(trigger).toHaveTextContent('Самара')
  },
}
export const Error: Story = { args: { error: 'Выберите регион' } }
export const Disabled: Story = { args: { disabled: true } }
export const WithController: Story = {
  render: () => <ControllerExample />,
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement)
    await userEvent.click(canvas.getByRole('combobox', { name: 'Регион' }))
    await userEvent.click(
      await within(document.body).findByRole('option', { name: 'Москва' }),
    )
    await userEvent.click(canvas.getByRole('button', { name: 'Применить' }))
    await expect(canvas.getByRole('status')).toHaveTextContent('Москва')
  },
}

export const Dark: Story = { ...Selection, globals: { theme: 'dark' } }
