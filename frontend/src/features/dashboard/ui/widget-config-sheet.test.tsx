import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { WidgetConfigSheet } from './widget-config-sheet'
import type { WidgetDetail } from '../api/dashboard-gateway'

const mockWidget: WidgetDetail = {
  id: 'w-1',
  title: 'Выручка за месяц',
  type: 'kpi_card',
  query_config: {
    dataset: 'sales',
    metric: 'revenue',
    dimension: null,
    date_range: '30d',
  },
  position: { x: 0, y: 0, w: 4, h: 2 },
  options: {},
}

describe('WidgetConfigSheet', () => {
  it('renders create mode with initial default fields', () => {
    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={vi.fn()}
        editingWidget={null}
        onSave={vi.fn()}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Добавить виджет' })).toBeInTheDocument()
    expect(screen.getByLabelText(/Название виджета/i)).toHaveValue('')
    expect(screen.getByTestId('submit-widget-btn')).toBeInTheDocument()
  })

  it('populates fields from editingWidget in edit mode', () => {
    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={vi.fn()}
        editingWidget={mockWidget}
        onSave={vi.fn()}
      />,
    )

    expect(screen.getByText('Настройка виджета')).toBeInTheDocument()
    expect(screen.getByLabelText(/Название виджета/i)).toHaveValue('Выручка за месяц')
  })

  it('validates title and submits valid form data', () => {
    const onSave = vi.fn()
    const onOpenChange = vi.fn()

    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={onOpenChange}
        editingWidget={null}
        onSave={onSave}
      />,
    )

    const titleInput = screen.getByLabelText(/Название виджета/i)
    fireEvent.change(titleInput, { target: { value: 'Маржинальность по категориям' } })

    const submitBtn = screen.getByTestId('submit-widget-btn')
    fireEvent.click(submitBtn)

    expect(onSave).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Маржинальность по категориям',
        dataset: 'sales',
        metric: 'revenue',
      }),
    )
  })

  it('calls onOpenChange(false) when cancel button is clicked', () => {
    const onOpenChange = vi.fn()
    render(
      <WidgetConfigSheet
        open={true}
        onOpenChange={onOpenChange}
        editingWidget={null}
        onSave={vi.fn()}
      />,
    )

    const cancelBtn = screen.getByRole('button', { name: /Отмена/i })
    fireEvent.click(cancelBtn)
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })
})
