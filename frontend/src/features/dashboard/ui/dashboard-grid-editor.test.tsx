import React from 'react'
import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { DashboardGridEditor } from './dashboard-grid-editor'
import type { WidgetDetail } from '../api/dashboard-gateway'

vi.mock('./widgets/widget-renderer', () => ({
  WidgetRenderer: ({ widget }: { widget: WidgetDetail }) => (
    <div data-testid={`rendered-${widget.id}`}>{widget.title}</div>
  ),
}))

const mockWidgets: WidgetDetail[] = [
  {
    id: 'w-1',
    title: 'Выручка',
    type: 'kpi_card',
    query_config: { dataset: 'sales', metric: 'revenue', date_range: '30d' },
    position: { x: 0, y: 0, w: 4, h: 2 },
    options: {},
  },
  {
    id: 'w-2',
    title: 'Динамика продаж',
    type: 'line_chart',
    query_config: {
      dataset: 'sales',
      metric: 'revenue',
      dimension: 'date',
      date_range: '30d',
    },
    position: { x: 4, y: 0, w: 8, h: 4 },
    options: {},
  },
]

describe('DashboardGridEditor', () => {
  it('renders all widgets with editing toolbars and controls', () => {
    render(
      <DashboardGridEditor
        widgets={mockWidgets}
        userId="user-1"
        workspaceId="ws-1"
        onMove={vi.fn()}
        onResize={vi.fn()}
        onReposition={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAddWidget={vi.fn()}
      />,
    )

    expect(screen.getAllByText('Выручка').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Динамика продаж').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByLabelText(/Настроить виджет/i)).toHaveLength(2)
    expect(screen.getAllByLabelText(/Удалить виджет/i)).toHaveLength(2)
  })

  it('triggers onEdit and onDelete callbacks when buttons are clicked', () => {
    const onEdit = vi.fn()
    const onDelete = vi.fn()

    render(
      <DashboardGridEditor
        widgets={mockWidgets}
        userId="user-1"
        workspaceId="ws-1"
        onMove={vi.fn()}
        onResize={vi.fn()}
        onReposition={vi.fn()}
        onEdit={onEdit}
        onDelete={onDelete}
        onAddWidget={vi.fn()}
      />,
    )

    const editBtns = screen.getAllByLabelText(/Настроить виджет/i)
    fireEvent.click(editBtns[0])
    expect(onEdit).toHaveBeenCalledWith(mockWidgets[0])

    const deleteBtns = screen.getAllByLabelText(/Удалить виджет/i)
    fireEvent.click(deleteBtns[0])
    expect(onDelete).toHaveBeenCalledWith('w-1')
  })

  it('triggers onMove and onResize callbacks', () => {
    const onMove = vi.fn()
    const onResize = vi.fn()

    render(
      <DashboardGridEditor
        widgets={mockWidgets}
        userId="user-1"
        workspaceId="ws-1"
        onMove={onMove}
        onResize={onResize}
        onReposition={vi.fn()}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        onAddWidget={vi.fn()}
      />,
    )

    // w-1 is at x=0, left button should be disabled, right button should call onMove
    const moveRightBtns = screen.getAllByLabelText(/Сдвинуть вправо/i)
    fireEvent.click(moveRightBtns[0])
    expect(onMove).toHaveBeenCalledWith('w-1', 'right')

    // Width increase button
    const expandWidthBtns = screen.getAllByLabelText(/Увеличить ширину/i)
    fireEvent.click(expandWidthBtns[0])
    expect(onResize).toHaveBeenCalledWith('w-1', 1, 0)
  })

  it('renders add widget prompt when grid is empty', () => {
    const onAddWidget = vi.fn()
    render(
      <DashboardGridEditor
        widgets={[]}
        userId="user-1"
        workspaceId="ws-1"
        onMove={vi.fn()}
        onResize={vi.fn()}
        onReposition={vi.fn()}
        onEdit={vi.fn()}
        onDelete={onAddWidget}
        onAddWidget={onAddWidget}
      />,
    )

    expect(screen.getByText(/Сетка пуста/i)).toBeInTheDocument()
    const addBtn = screen.getByRole('button', { name: /Добавить первый виджет/i })
    fireEvent.click(addBtn)
    expect(onAddWidget).toHaveBeenCalledTimes(1)
  })
})
