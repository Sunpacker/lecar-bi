import React from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { DashboardViewer } from './dashboard-viewer'
import { dashboardGateway, type DashboardDetail } from '../api/dashboard-gateway'
import * as widgetDataLoader from '../model/widget-data-loader'

vi.mock('../api/dashboard-gateway', () => ({
  dashboardGateway: {
    update: vi.fn(),
    listSavedViews: vi.fn().mockResolvedValue([]),
  },
}))

vi.spyOn(widgetDataLoader, 'loadWidgetData').mockResolvedValue({
  loading: false,
  kpi: { value: 1500000, formatted: '1 500 000 ₽', subtitle: 'за 30 дней' },
  chartData: [
    { name: '2026-09-01', value: 100000 },
    { name: '2026-09-02', value: 120000 },
  ],
})

const initialDashboard: DashboardDetail = {
  id: 'dash-test-1',
  workspace_id: 'ws-1',
  title: 'Коммерческий дашборд',
  description: 'Исходные ключевые показатели',
  created_at: '2026-09-22T10:00:00Z',
  updated_at: '2026-09-22T10:00:00Z',
  widgets: [
    {
      id: 'w-1',
      title: 'Выручка',
      type: 'kpi_card',
      query_config: { dataset: 'sales', metric: 'revenue', date_range: '30d' },
      position: { x: 0, y: 0, w: 4, h: 2 },
      options: {},
    },
  ],
}

describe('Dashboard Builder Full E2E Flow', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('executes complete builder flow: edit mode -> reconfigure -> add widget -> move -> resize -> save', async () => {
    vi.mocked(dashboardGateway.update).mockResolvedValueOnce({
      ...initialDashboard,
      title: 'Обновленный коммерческий дашборд',
      description: 'Новое описание дашборда',
      widgets: [
        {
          id: 'w-1',
          title: 'Выручка за месяц',
          type: 'kpi_card',
          query_config: { dataset: 'sales', metric: 'revenue', date_range: '30d' },
          position: { x: 1, y: 0, w: 5, h: 2 },
          options: {},
        },
        {
          id: 'w-new-2',
          title: 'Динамика продаж',
          type: 'line_chart',
          query_config: {
            dataset: 'sales',
            metric: 'revenue',
            dimension: 'date',
            date_range: '30d',
          },
          position: { x: 0, y: 2, w: 8, h: 4 },
          options: {},
        },
      ],
    })

    render(
      <DashboardViewer dashboard={initialDashboard} userId="user-1" workspaceId="ws-1" />,
    )

    // 1. Initial View Mode check
    expect(screen.getByText('Коммерческий дашборд')).toBeInTheDocument()
    expect(screen.getByText('Исходные ключевые показатели')).toBeInTheDocument()
    expect(screen.getByText('Выручка')).toBeInTheDocument()

    // 2. Switch to Edit Mode
    const editBtn = screen.getByRole('button', { name: /Редактировать/i })
    fireEvent.click(editBtn)

    expect(screen.getByText('Режим редактирования')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Добавить виджет/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Сохранить/i })).toBeInTheDocument()

    // 3. Edit title and description
    const titleInput = screen.getByTestId('dashboard-title-input')
    const descInput = screen.getByTestId('dashboard-desc-input')
    fireEvent.change(titleInput, {
      target: { value: 'Обновленный коммерческий дашборд' },
    })
    fireEvent.change(descInput, { target: { value: 'Новое описание дашборда' } })

    // 4. Reposition & Resize existing widget (w-1)
    const moveRightBtn = screen.getByRole('button', { name: 'Сдвинуть вправо' })
    fireEvent.click(moveRightBtn)

    const increaseWidthBtn = screen.getByRole('button', { name: 'Увеличить ширину' })
    fireEvent.click(increaseWidthBtn)

    // 5. Open WidgetConfigSheet to edit existing widget w-1
    const editWidgetBtn = screen.getByRole('button', { name: 'Настроить виджет' })
    fireEvent.click(editWidgetBtn)

    expect(screen.getByText('Настройка виджета')).toBeInTheDocument()
    const widgetTitleInput = screen.getByLabelText(/Название виджета/i)
    fireEvent.change(widgetTitleInput, { target: { value: 'Выручка за месяц' } })

    const applyWidgetBtn = screen.getByTestId('submit-widget-btn')
    fireEvent.click(applyWidgetBtn)

    // 6. Open WidgetConfigSheet to add a NEW widget
    const addWidgetBtn = screen.getByRole('button', { name: /Добавить виджет/i })
    fireEvent.click(addWidgetBtn)

    expect(screen.getByRole('heading', { name: 'Добавить виджет' })).toBeInTheDocument()
    const newWidgetTitleInput = screen.getByLabelText(/Название виджета/i)
    fireEvent.change(newWidgetTitleInput, { target: { value: 'Динамика продаж' } })

    const applyNewWidgetBtn = screen.getByTestId('submit-widget-btn')
    fireEvent.click(applyNewWidgetBtn)

    // Verify both widgets exist in editor (both in card header and embedded live widget)
    expect(screen.getAllByText('Выручка за месяц').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Динамика продаж').length).toBeGreaterThanOrEqual(1)

    // 7. Save Dashboard and assert clean API payload
    const saveBtn = screen.getByRole('button', { name: /Сохранить/i })
    fireEvent.click(saveBtn)

    await waitFor(() => {
      expect(dashboardGateway.update).toHaveBeenCalledTimes(1)
      const callArgs = vi.mocked(dashboardGateway.update).mock.calls[0]
      expect(callArgs[0]).toBe('dash-test-1')
      expect(callArgs[1]).toBe('user-1')
      expect(callArgs[3]).toBe('ws-1')

      const payload = callArgs[2]
      expect(payload.title).toBe('Обновленный коммерческий дашборд')
      expect(payload.description).toBe('Новое описание дашборда')
      expect(payload.widgets).toHaveLength(2)

      // Invariants: clean semantic properties, no DOM/React leaks
      payload.widgets.forEach((w) => {
        expect(w).toHaveProperty('title')
        expect(w).toHaveProperty('type')
        expect(w).toHaveProperty('position')
        expect(w).toHaveProperty('query_config')
        expect(w).not.toHaveProperty('className')
        expect(w).not.toHaveProperty('style')
        expect(w).not.toHaveProperty('children')

        // 12-column grid bounds
        expect(w.position.x).toBeGreaterThanOrEqual(0)
        expect(w.position.x + w.position.w).toBeLessThanOrEqual(12)
        expect(w.position.w).toBeGreaterThanOrEqual(1)
        expect(w.position.h).toBeGreaterThanOrEqual(1)
      })
    })
  })

  it('allows discarding builder changes without mutating original state', () => {
    render(
      <DashboardViewer dashboard={initialDashboard} userId="user-1" workspaceId="ws-1" />,
    )

    // Switch to edit mode
    fireEvent.click(screen.getByRole('button', { name: /Редактировать/i }))

    // Modify title
    const titleInput = screen.getByTestId('dashboard-title-input')
    fireEvent.change(titleInput, { target: { value: 'Случайное изменение' } })

    // Click delete on w-1
    const deleteBtn = screen.getByRole('button', { name: 'Удалить виджет' })
    fireEvent.click(deleteBtn)

    expect(screen.queryByText('Выручка')).not.toBeInTheDocument()

    // Discard changes
    const cancelBtn = screen.getByRole('button', { name: /Отмена/i })
    fireEvent.click(cancelBtn)

    // Mode is back to view and original widget is intact
    expect(screen.getByRole('button', { name: /Редактировать/i })).toBeInTheDocument()
    expect(screen.getByText('Коммерческий дашборд')).toBeInTheDocument()
    expect(screen.getByText('Выручка')).toBeInTheDocument()
    expect(dashboardGateway.update).not.toHaveBeenCalled()
  })
})
