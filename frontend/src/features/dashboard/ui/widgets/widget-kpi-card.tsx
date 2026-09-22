import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface WidgetKpiCardProps {
  title: string
  value: string
  subtitle?: string
}

export function WidgetKpiCard({ title, value, subtitle }: WidgetKpiCardProps) {
  return (
    <Card className="h-full flex flex-col justify-between border-border bg-card/60 shadow-xs">
      <CardHeader className="pb-2">
        <CardTitle className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent className="pt-0">
        <div className="text-2xl font-bold tracking-tight text-foreground">{value}</div>
        {subtitle && (
          <p className="mt-1 text-xs text-muted-foreground leading-normal">{subtitle}</p>
        )}
      </CardContent>
    </Card>
  )
}
