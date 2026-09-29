import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from './field'
import { Input } from './input'

const meta = {
  title: 'UI/Field',
  component: Field,
  parameters: {
    docs: {
      description: {
        component:
          'Композиция подписи, контрола, подсказки и ошибки. Импорт: `@/components/ui/field`. Пример: `<Field><FieldLabel htmlFor="x">Имя</FieldLabel><Input id="x" /></Field>`.',
      },
    },
  },
} satisfies Meta<typeof Field>
export default meta
type Story = StoryObj<typeof meta>
export const States: Story = {
  render: () => (
    <FieldGroup className="max-w-lg">
      <Field>
        <FieldLabel htmlFor="field-name">Имя</FieldLabel>
        <Input id="field-name" />
        <FieldDescription>Как к вам обращаться</FieldDescription>
      </Field>
      <Field data-invalid>
        <FieldLabel htmlFor="field-error">Код</FieldLabel>
        <Input id="field-error" aria-invalid aria-describedby="field-error-message" />
        <FieldError id="field-error-message">Поле обязательно</FieldError>
      </Field>
      <Field data-disabled>
        <FieldLabel htmlFor="field-disabled">Недоступно</FieldLabel>
        <Input id="field-disabled" disabled />
      </Field>
    </FieldGroup>
  ),
}
export const Responsive: Story = {
  render: () => (
    <FieldGroup className="max-w-xl">
      <Field orientation="responsive">
        <FieldLabel htmlFor="field-responsive">
          Очень длинная подпись поля, которая должна корректно переноситься
        </FieldLabel>
        <Input id="field-responsive" />
      </Field>
    </FieldGroup>
  ),
}

export const Dark: Story = { ...States, globals: { theme: 'dark' } }

export const Narrow: Story = {
  render: () => (
    <div className="w-[280px]">
      <FieldGroup>
        <Field orientation="responsive">
          <FieldLabel htmlFor="field-narrow">
            Очень длинная подпись поля на узком экране
          </FieldLabel>
          <Input id="field-narrow" />
        </Field>
      </FieldGroup>
    </div>
  ),
}
