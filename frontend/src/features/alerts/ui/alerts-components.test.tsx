import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'
import { AlertSummaryCards } from './alert-summary-cards'
import { AlertTable } from './alert-table'
import { AlertRuleList } from './alert-rule-list'
import { AlertResolveDialog } from './alert-resolve-dialog'
import type { Alert, AlertRule, AlertSummaryResponse } from '../api/alerts-gateway'

describe('Alerts UI Components', () => {
  const mockSummary: AlertSummaryResponse = {
    total_active: 5,
    critical_count: 2,
    warning_count: 2,
    info_count: 1,
    acknowledged_count: 2,
  }

  const mockAlerts: Alert[] = [
    {
      id: 'alt-1',
      workspace_id: 'ws-1',
      rule_id: 'rule-1',
      rule_name: 'Аут-оф-сток',
      severity: 'critical',
      status: 'open',
      dedup_fingerprint: 'fp-1',
      product_id: 'prod-1',
      product_name: 'Масляный фильтр BOSCH',
      product_sku: 'FILT-001',
      warehouse_id: 'wh-1',
      warehouse_name: 'Центральный склад',
      current_value: 0,
      threshold_value: 0,
      context_data: { target: 'inventory' },
      triggered_at: '2026-09-23T10:00:00Z',
      acknowledged_at: null,
      acknowledged_by: null,
      resolved_at: null,
      resolved_by: null,
      resolution_note: null,
      created_at: '2026-09-23T10:00:00Z',
      updated_at: '2026-09-23T10:00:00Z',
    },
    {
      id: 'alt-2',
      workspace_id: 'ws-1',
      rule_id: 'rule-2',
      rule_name: 'Залежалый товар',
      severity: 'warning',
      status: 'acknowledged',
      dedup_fingerprint: 'fp-2',
      product_id: 'prod-2',
      product_name: 'Тормозные колодки Brembo',
      product_sku: 'BRK-002',
      warehouse_id: 'wh-1',
      warehouse_name: 'Центральный склад',
      current_value: 85,
      threshold_value: 60,
      context_data: { target: 'inventory' },
      triggered_at: '2026-09-23T09:00:00Z',
      acknowledged_at: '2026-09-23T09:30:00Z',
      acknowledged_by: 'user-1',
      resolved_at: null,
      resolved_by: null,
      resolution_note: null,
      created_at: '2026-09-23T09:00:00Z',
      updated_at: '2026-09-23T09:30:00Z',
    },
  ]

  const mockRules: AlertRule[] = [
    {
      id: 'rule-1',
      workspace_id: 'ws-1',
      name: 'Критический дефицит (DOS < 7)',
      description: 'Менее недели продаж',
      rule_type: 'critical_stock',
      severity: 'critical',
      metric: 'days_of_stock',
      comparator: 'lt',
      threshold_value: 7,
      warehouse_id: 'wh-1',
      is_enabled: true,
      created_at: '2026-09-23T08:00:00Z',
      updated_at: '2026-09-23T08:00:00Z',
    },
  ]

  it('renders KPI summary cards with values', () => {
    render(<AlertSummaryCards summary={mockSummary} />)

    expect(screen.getByText('Активные алерты')).toBeDefined()
    expect(screen.getByText('Критический дефицит')).toBeDefined()
    expect(screen.getByText('Предупреждения')).toBeDefined()
    expect(screen.getByText('В работе')).toBeDefined()

    expect(screen.getByText('5')).toBeDefined()
    expect(screen.getAllByText('2')).toHaveLength(3) // critical_count, warning_count, acknowledged_count
  })

  it('renders alert table and triggers actions', async () => {
    const onAcknowledge = vi.fn().mockResolvedValue(undefined)
    const onOpenResolve = vi.fn()
    const onStatusChange = vi.fn()
    const onSeverityChange = vi.fn()

    render(
      <AlertTable
        alerts={mockAlerts}
        onAcknowledge={onAcknowledge}
        onOpenResolve={onOpenResolve}
        statusFilter="active"
        onStatusChange={onStatusChange}
        onSeverityChange={onSeverityChange}
      />,
    )

    // Check items rendered
    expect(screen.getByText('Масляный фильтр BOSCH')).toBeDefined()
    expect(screen.getByText('FILT-001')).toBeDefined()
    expect(screen.getByText('Тормозные колодки Brembo')).toBeDefined()

    // Test acknowledge button
    const ackButton = screen.getByText('В работу')
    fireEvent.click(ackButton)
    expect(onAcknowledge).toHaveBeenCalledWith('alt-1')

    // Test resolve button
    const resolveButtons = screen.getAllByText('Закрыть')
    fireEvent.click(resolveButtons[0])
    expect(onOpenResolve).toHaveBeenCalledWith(mockAlerts[0])

    // Test tab click
    const resolvedTab = screen.getByText('Решённые')
    fireEvent.click(resolvedTab)
    expect(onStatusChange).toHaveBeenCalledWith('resolved')
  })

  it('renders inventory link with correct parameters', () => {
    render(
      <AlertTable
        alerts={mockAlerts}
        onAcknowledge={vi.fn()}
        onOpenResolve={vi.fn()}
        statusFilter="active"
        onStatusChange={vi.fn()}
        onSeverityChange={vi.fn()}
      />,
    )

    const inventoryLinks = screen.getAllByRole('link', { name: /Запасы/i })
    expect(inventoryLinks[0].getAttribute('href')).toBe(
      '/inventory?warehouse_id=wh-1&search=FILT-001',
    )
  })

  it('renders alert rules list and triggers toggle and create', () => {
    const onToggleRule = vi.fn().mockResolvedValue(undefined)
    const onDeleteRule = vi.fn().mockResolvedValue(undefined)
    const onEvaluate = vi.fn().mockResolvedValue(undefined)
    const onOpenCreate = vi.fn()

    render(
      <AlertRuleList
        rules={mockRules}
        onToggleRule={onToggleRule}
        onDeleteRule={onDeleteRule}
        onEvaluate={onEvaluate}
        onOpenCreate={onOpenCreate}
      />,
    )

    expect(screen.getByText('Критический дефицит (DOS < 7)')).toBeDefined()
    expect(screen.getByText('Менее недели продаж')).toBeDefined()

    const toggleButton = screen.getByText('Вкл')
    fireEvent.click(toggleButton)
    expect(onToggleRule).toHaveBeenCalledWith('rule-1')

    const evalButton = screen.getByText('Запустить оценку')
    fireEvent.click(evalButton)
    expect(onEvaluate).toHaveBeenCalled()

    const createButton = screen.getByText('+ Создать правило')
    fireEvent.click(createButton)
    expect(onOpenCreate).toHaveBeenCalled()
  })

  it('handles alert resolve dialog submission', async () => {
    const onConfirm = vi.fn().mockResolvedValue(undefined)
    const onOpenChange = vi.fn()

    render(
      <AlertResolveDialog
        open={true}
        onOpenChange={onOpenChange}
        alertId="alt-1"
        alertTitle="Дефицит фильтра"
        onConfirm={onConfirm}
      />,
    )

    expect(screen.getByText('Закрыть алерт')).toBeDefined()
    const textarea = screen.getByPlaceholderText(/Заказ оформлен у поставщика/i)
    fireEvent.change(textarea, { target: { value: 'Поставка получена' } })

    const submitButton = screen.getByText('Подтвердить закрытие')
    fireEvent.click(submitButton)

    await waitFor(() => {
      expect(onConfirm).toHaveBeenCalledWith('alt-1', 'Поставка получена')
      expect(onOpenChange).toHaveBeenCalledWith(false)
    })
  })

  it('hides mutation buttons in alert table when canManageAlerts is false (viewer)', () => {
    render(
      <AlertTable
        alerts={mockAlerts}
        onAcknowledge={vi.fn()}
        onOpenResolve={vi.fn()}
        statusFilter="active"
        onStatusChange={vi.fn()}
        onSeverityChange={vi.fn()}
        canManageAlerts={false}
      />,
    )

    // Items and links are visible
    expect(screen.getByText('Масляный фильтр BOSCH')).toBeDefined()
    expect(screen.getAllByRole('link', { name: /Запасы/i })).toHaveLength(2)

    // Action mutation buttons must NOT be rendered for viewer
    expect(screen.queryByText('В работу')).toBeNull()
    expect(screen.queryByText('Закрыть')).toBeNull()
  })

  it('hides rule mutations and create/eval buttons when canManageRules is false (viewer)', () => {
    render(
      <AlertRuleList
        rules={mockRules}
        onToggleRule={vi.fn()}
        onDeleteRule={vi.fn()}
        onEvaluate={vi.fn()}
        onOpenCreate={vi.fn()}
        canManageRules={false}
      />,
    )

    // Rule information is visible
    expect(screen.getByText('Критический дефицит (DOS < 7)')).toBeDefined()

    // Header buttons are hidden
    expect(screen.queryByText('Запустить оценку')).toBeNull()
    expect(screen.queryByText('+ Создать правило')).toBeNull()

    // Row action buttons are hidden (replaced by dash)
    expect(screen.queryByText('Вкл')).toBeNull()
    expect(screen.queryByTitle('Удалить правило')).toBeNull()
    expect(screen.getByText('—')).toBeDefined()
  })
})
