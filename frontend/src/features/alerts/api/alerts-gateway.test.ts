import { describe, it, expect, vi, beforeEach } from 'vitest'
import { alertsGateway } from './alerts-gateway'
import { analyticsClient } from '../../../shared/api/analytics-client'

vi.mock('../../../shared/api/analytics-client', () => ({
  analyticsClient: {
    GET: vi.fn(),
    POST: vi.fn(),
    PUT: vi.fn(),
    DELETE: vi.fn(),
  },
}))

describe('alertsGateway', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches alert rules list with headers and query', async () => {
    const mockResponse = {
      items: [
        {
          id: 'rule-1',
          workspace_id: 'ws-1',
          name: 'Дефицит',
          rule_type: 'critical_stock',
          severity: 'critical',
          condition: { metric: 'days_of_stock', comparator: 'lt', threshold_value: 7 },
          scope: {},
          is_enabled: true,
          created_at: '2026-09-23T00:00:00Z',
          updated_at: '2026-09-23T00:00:00Z',
        },
      ],
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockResponse,
      error: undefined,
    } as any)

    const result = await alertsGateway.getRules('user-1', 'ws-1', true)

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/alert-rules',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            is_enabled: true,
          },
        },
      }),
    )
    expect(result).toEqual(mockResponse)
  })

  it('creates an alert rule with payload', async () => {
    const mockRule = {
      rule: {
        id: 'rule-new',
        workspace_id: 'ws-1',
        name: 'Новое правило',
        rule_type: 'out_of_stock',
        severity: 'critical',
        condition: {
          metric: 'quantity_available',
          comparator: 'lte',
          threshold_value: 0,
        },
        scope: {},
        is_enabled: true,
        created_at: '2026-09-23T00:00:00Z',
        updated_at: '2026-09-23T00:00:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockRule,
      error: undefined,
    } as any)

    const payload = {
      name: 'Новое правило',
      rule_type: 'out_of_stock' as const,
      severity: 'critical' as const,
      metric: 'quantity_available' as const,
      comparator: 'lte' as const,
      threshold_value: 0,
      is_enabled: true,
    }

    const result = await alertsGateway.createRule('user-1', 'ws-1', payload)

    expect(analyticsClient.POST).toHaveBeenCalledWith(
      '/alert-rules',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        body: payload,
      }),
    )
    expect(result).toEqual(mockRule)
  })

  it('updates an alert rule', async () => {
    const mockRule = {
      rule: {
        id: 'rule-1',
        workspace_id: 'ws-1',
        name: 'Обновленное правило',
        rule_type: 'critical_stock',
        severity: 'warning',
        condition: { metric: 'days_of_stock', comparator: 'lt', threshold_value: 10 },
        scope: {},
        is_enabled: true,
        created_at: '2026-09-23T00:00:00Z',
        updated_at: '2026-09-23T00:00:00Z',
      },
    }

    vi.mocked(analyticsClient.PUT).mockResolvedValueOnce({
      data: mockRule,
      error: undefined,
    } as any)

    const payload = {
      name: 'Обновленное правило',
      severity: 'warning' as const,
      threshold_value: 10,
    }

    const result = await alertsGateway.updateRule('user-1', 'ws-1', 'rule-1', payload)

    expect(analyticsClient.PUT).toHaveBeenCalledWith(
      '/alert-rules/{id}',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: { path: { id: 'rule-1' } },
        body: payload,
      }),
    )
    expect(result).toEqual(mockRule)
  })

  it('deletes an alert rule', async () => {
    vi.mocked(analyticsClient.DELETE).mockResolvedValueOnce({
      data: undefined,
      error: undefined,
    } as any)

    await alertsGateway.deleteRule('user-1', 'ws-1', 'rule-1')

    expect(analyticsClient.DELETE).toHaveBeenCalledWith(
      '/alert-rules/{id}',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: { path: { id: 'rule-1' } },
      }),
    )
  })

  it('toggles an alert rule status', async () => {
    const mockRule = {
      rule: {
        id: 'rule-1',
        workspace_id: 'ws-1',
        name: 'Правило',
        rule_type: 'critical_stock',
        severity: 'critical',
        condition: { metric: 'days_of_stock', comparator: 'lt', threshold_value: 7 },
        scope: {},
        is_enabled: false,
        created_at: '2026-09-23T00:00:00Z',
        updated_at: '2026-09-23T00:00:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockRule,
      error: undefined,
    } as any)

    const result = await alertsGateway.toggleRule('user-1', 'ws-1', 'rule-1')

    expect(analyticsClient.POST).toHaveBeenCalledWith(
      '/alert-rules/{id}/toggle',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: { path: { id: 'rule-1' } },
      }),
    )
    expect(result).toEqual(mockRule)
  })

  it('triggers evaluate rules', async () => {
    const mockResult = {
      rules_evaluated: 4,
      alerts_triggered: 2,
      alerts_created: 1,
      alerts_updated: 1,
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockResult,
      error: undefined,
    } as any)

    const result = await alertsGateway.evaluateRules('user-1', 'ws-1')

    expect(analyticsClient.POST).toHaveBeenCalledWith(
      '/alert-rules/evaluate',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
      }),
    )
    expect(result).toEqual(mockResult)
  })

  it('fetches alerts summary', async () => {
    const mockSummary = {
      total_active: 3,
      critical_count: 2,
      warning_count: 1,
      info_count: 0,
      acknowledged_count: 1,
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockSummary,
      error: undefined,
    } as any)

    const result = await alertsGateway.getSummary('user-1', 'ws-1')

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/alerts/summary',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
      }),
    )
    expect(result).toEqual(mockSummary)
  })

  it('fetches alerts list with filters', async () => {
    const mockAlerts = {
      items: [
        {
          id: 'alert-1',
          workspace_id: 'ws-1',
          rule_id: 'rule-1',
          rule_name: 'Дефицит',
          severity: 'critical',
          status: 'open',
          dedup_fingerprint: 'fp-1',
          product_id: 'prod-1',
          product_name: 'Фильтр BOSCH',
          product_sku: 'FILT-001',
          warehouse_id: 'wh-1',
          warehouse_name: 'Основной склад',
          current_value: 0,
          threshold_value: 0,
          context_data: {},
          triggered_at: '2026-09-23T10:00:00Z',
          created_at: '2026-09-23T10:00:00Z',
          updated_at: '2026-09-23T10:00:00Z',
        },
      ],
      pagination: {
        page: 1,
        per_page: 20,
        total: 1,
        total_pages: 1,
      },
    }

    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: mockAlerts,
      error: undefined,
    } as any)

    const result = await alertsGateway.getAlerts('user-1', 'ws-1', {
      status: 'active',
      severity: 'critical',
      warehouseId: 'wh-1',
    })

    expect(analyticsClient.GET).toHaveBeenCalledWith(
      '/alerts',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: {
          query: {
            status: 'active',
            severity: 'critical',
            warehouse_id: 'wh-1',
          },
        },
      }),
    )
    expect(result).toEqual(mockAlerts)
  })

  it('acknowledges an alert', async () => {
    const mockAlert = {
      alert: {
        id: 'alert-1',
        status: 'acknowledged',
        acknowledged_by: 'user-1',
        acknowledged_at: '2026-09-23T11:00:00Z',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockAlert,
      error: undefined,
    } as any)

    const result = await alertsGateway.acknowledgeAlert('user-1', 'ws-1', 'alert-1')

    expect(analyticsClient.POST).toHaveBeenCalledWith(
      '/alerts/{id}/acknowledge',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: { path: { id: 'alert-1' } },
      }),
    )
    expect(result).toEqual(mockAlert)
  })

  it('resolves an alert with note', async () => {
    const mockAlert = {
      alert: {
        id: 'alert-1',
        status: 'resolved',
        resolved_by: 'user-1',
        resolved_at: '2026-09-23T12:00:00Z',
        resolution_note: 'Заказано у поставщика',
      },
    }

    vi.mocked(analyticsClient.POST).mockResolvedValueOnce({
      data: mockAlert,
      error: undefined,
    } as any)

    const result = await alertsGateway.resolveAlert('user-1', 'ws-1', 'alert-1', {
      resolution_note: 'Заказано у поставщика',
    })

    expect(analyticsClient.POST).toHaveBeenCalledWith(
      '/alerts/{id}/resolve',
      expect.objectContaining({
        headers: {
          'X-User-Id': 'user-1',
          'X-Workspace-Id': 'ws-1',
        },
        params: { path: { id: 'alert-1' } },
        body: { resolution_note: 'Заказано у поставщика' },
      }),
    )
    expect(result).toEqual(mockAlert)
  })

  it('throws error when API call fails', async () => {
    vi.mocked(analyticsClient.GET).mockResolvedValueOnce({
      data: undefined,
      error: { message: 'Alert rule not found' },
    } as any)

    await expect(alertsGateway.getRule('user-1', 'ws-1', 'invalid-id')).rejects.toThrow(
      'Alert rule not found',
    )
  })
})
