'use client'

import React, { useState, useEffect, useCallback } from 'react'
import {
  alertsGateway,
  type Alert,
  type AlertRule,
  type AlertSummaryResponse,
  type CreateAlertRuleRequest,
} from '../api/alerts-gateway'
import { AlertSummaryCards } from './alert-summary-cards'
import { AlertTable } from './alert-table'
import { AlertRuleList } from './alert-rule-list'
import { AlertRuleDialog } from './alert-rule-dialog'
import { AlertResolveDialog } from './alert-resolve-dialog'
import { Button } from '@/components/ui/button'
import { Play, Bell, ShieldAlert } from 'lucide-react'

interface AlertsViewProps {
  userId: string
  workspaceId: string
}

export function AlertsView({ userId, workspaceId }: AlertsViewProps) {
  const [activeTab, setActiveTab] = useState<'alerts' | 'rules'>('alerts')
  const [summary, setSummary] = useState<AlertSummaryResponse | null>(null)
  const [alerts, setAlerts] = useState<Alert[]>([])
  const [rules, setRules] = useState<AlertRule[]>([])

  const [isLoading, setIsLoading] = useState(true)
  const [isEvaluating, setIsEvaluating] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // Filters
  const [statusFilter, setStatusFilter] = useState('active')
  const [severityFilter, setSeverityFilter] = useState<string | undefined>()

  // Dialogs
  const [isRuleDialogOpen, setIsRuleDialogOpen] = useState(false)
  const [isResolveDialogOpen, setIsResolveDialogOpen] = useState(false)
  const [resolvingAlert, setResolvingAlert] = useState<Alert | null>(null)
  const [feedbackMessage, setFeedbackMessage] = useState<string | null>(null)

  const reloadData = useCallback(async () => {
    try {
      const [summaryData, alertsData, rulesData] = await Promise.all([
        alertsGateway.getSummary(userId, workspaceId),
        alertsGateway.getAlerts(userId, workspaceId, {
          status: statusFilter as any,
          severity: severityFilter as any,
        }),
        alertsGateway.getRules(userId, workspaceId),
      ])
      setSummary(summaryData)
      setAlerts(alertsData.items)
      setRules(rulesData.items)
    } catch (err) {
      console.error('Failed to load alert data:', err)
    }
  }, [userId, workspaceId, statusFilter, severityFilter])

  useEffect(() => {
    let isCancelled = false

    async function loadInitial() {
      try {
        const [summaryData, alertsData, rulesData] = await Promise.all([
          alertsGateway.getSummary(userId, workspaceId),
          alertsGateway.getAlerts(userId, workspaceId, {
            status: statusFilter as any,
            severity: severityFilter as any,
          }),
          alertsGateway.getRules(userId, workspaceId),
        ])
        if (!isCancelled) {
          setSummary(summaryData)
          setAlerts(alertsData.items)
          setRules(rulesData.items)
        }
      } catch (err) {
        if (!isCancelled) {
          console.error('Failed to load initial alerts:', err)
        }
      } finally {
        if (!isCancelled) {
          setIsLoading(false)
        }
      }
    }

    loadInitial()

    return () => {
      isCancelled = true
    }
  }, [userId, workspaceId, statusFilter, severityFilter])

  const showNotification = (msg: string) => {
    setFeedbackMessage(msg)
    setTimeout(() => setFeedbackMessage(null), 4000)
  }

  const handleAcknowledge = async (alertId: string) => {
    try {
      await alertsGateway.acknowledgeAlert(userId, workspaceId, alertId)
      showNotification('Алерт принят в работу')
      await reloadData()
    } catch (err: any) {
      alert(err?.message ?? 'Ошибка принятия алерта в работу')
    }
  }

  const handleOpenResolve = (alertItem: Alert) => {
    setResolvingAlert(alertItem)
    setIsResolveDialogOpen(true)
  }

  const handleConfirmResolve = async (alertId: string, note: string) => {
    setIsSubmitting(true)
    try {
      await alertsGateway.resolveAlert(userId, workspaceId, alertId, {
        resolution_note: note || undefined,
      })
      showNotification('Алерт успешно закрыт')
      await reloadData()
    } catch (err: any) {
      alert(err?.message ?? 'Ошибка закрытия алерта')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleToggleRule = async (ruleId: string) => {
    try {
      await alertsGateway.toggleRule(userId, workspaceId, ruleId)
      showNotification('Статус правила изменён')
      await reloadData()
    } catch (err: any) {
      alert(err?.message ?? 'Ошибка переключения правила')
    }
  }

  const handleDeleteRule = async (ruleId: string) => {
    if (!confirm('Вы уверены, что хотите удалить это правило?')) return

    try {
      await alertsGateway.deleteRule(userId, workspaceId, ruleId)
      showNotification('Правило удалено')
      await reloadData()
    } catch (err: any) {
      alert(err?.message ?? 'Ошибка удаления правила')
    }
  }

  const handleEvaluate = async () => {
    setIsEvaluating(true)
    try {
      const res = await alertsGateway.evaluateRules(userId, workspaceId)
      showNotification(
        `Оценка завершена: проверено ${res.rules_evaluated} правил, сработало ${res.alerts_triggered} алертов (${res.alerts_created} новых)`,
      )
      await reloadData()
    } catch (err: any) {
      alert(err?.message ?? 'Ошибка оценки правил')
    } finally {
      setIsEvaluating(false)
    }
  }

  const handleSaveRule = async (payload: CreateAlertRuleRequest) => {
    setIsSubmitting(true)
    try {
      await alertsGateway.createRule(userId, workspaceId, payload)
      showNotification('Правило успешно создано')
      await reloadData()
    } catch (err: any) {
      alert(err?.message ?? 'Ошибка создания правила')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* KPI Cards */}
      <AlertSummaryCards summary={summary} isLoading={isLoading} />

      {/* Notification banner */}
      {feedbackMessage && (
        <div className="p-3 bg-primary/10 border border-primary/20 rounded-md text-sm text-foreground flex items-center justify-between animate-in fade-in">
          <span>{feedbackMessage}</span>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setFeedbackMessage(null)}
            className="h-6 px-2 text-xs"
          >
            ✕
          </Button>
        </div>
      )}

      {/* Navigation tabs & Action Bar */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-3">
        <div className="flex items-center gap-2">
          <Button
            variant={activeTab === 'alerts' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setActiveTab('alerts')}
            className="flex items-center gap-2"
          >
            <ShieldAlert className="h-4 w-4" />
            Инциденты и дефициты
          </Button>

          <Button
            variant={activeTab === 'rules' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setActiveTab('rules')}
            className="flex items-center gap-2"
          >
            <Bell className="h-4 w-4" />
            Правила мониторинга ({rules.length})
          </Button>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={handleEvaluate}
            disabled={isEvaluating}
            className="text-xs"
          >
            <Play
              className={`h-3.5 w-3.5 mr-1.5 ${isEvaluating ? 'animate-spin' : ''}`}
            />
            {isEvaluating ? 'Оценка...' : 'Пересчитать алерты'}
          </Button>

          {activeTab === 'rules' && (
            <Button
              size="sm"
              onClick={() => setIsRuleDialogOpen(true)}
              className="text-xs"
            >
              + Добавить правило
            </Button>
          )}
        </div>
      </div>

      {/* Main Tab Content */}
      {activeTab === 'alerts' ? (
        <AlertTable
          alerts={alerts}
          isLoading={isLoading}
          onAcknowledge={handleAcknowledge}
          onOpenResolve={handleOpenResolve}
          statusFilter={statusFilter}
          onStatusChange={setStatusFilter}
          severityFilter={severityFilter}
          onSeverityChange={setSeverityFilter}
        />
      ) : (
        <AlertRuleList
          rules={rules}
          isLoading={isLoading}
          isEvaluating={isEvaluating}
          onToggleRule={handleToggleRule}
          onDeleteRule={handleDeleteRule}
          onEvaluate={handleEvaluate}
          onOpenCreate={() => setIsRuleDialogOpen(true)}
        />
      )}

      {/* Dialogs */}
      <AlertRuleDialog
        open={isRuleDialogOpen}
        onOpenChange={setIsRuleDialogOpen}
        onSave={handleSaveRule}
        isSubmitting={isSubmitting}
      />

      <AlertResolveDialog
        open={isResolveDialogOpen}
        onOpenChange={setIsResolveDialogOpen}
        alertId={resolvingAlert?.id ?? null}
        alertTitle={resolvingAlert?.product_name ?? resolvingAlert?.rule_name}
        onConfirm={handleConfirmResolve}
        isSubmitting={isSubmitting}
      />
    </div>
  )
}
