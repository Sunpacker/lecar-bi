import type { Meta, StoryObj } from '@storybook/nextjs-vite'
import { TextField } from './text-field'

const meta = {
  title: 'Compositions/Forms/TextField',
  component: TextField,
  args: { label: 'Email', placeholder: 'name@example.com' },
  parameters: {
    docs: {
      description: {
        component:
          'Input с подписью, подсказкой, ошибкой и ref; подходит для Controller. Импорт: `@/src/shared/ui`. Пример: `<TextField label="Email" type="email" error={errors.email?.message} {...field} />`.',
      },
    },
  },
} satisfies Meta<typeof TextField>
export default meta
type Story = StoryObj<typeof meta>
export const Default: Story = { args: { hint: 'Для уведомлений' } }
export const Error: Story = {
  args: { error: 'Введите корректный email', defaultValue: 'abc' },
}
export const Disabled: Story = { args: { disabled: true, defaultValue: 'Недоступно' } }

export const Dark: Story = { ...Default, globals: { theme: 'dark' } }
