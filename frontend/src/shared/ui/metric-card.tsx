import type { ReactNode } from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'

export interface MetricCardProps {
  title: string
  value: ReactNode
  description?: ReactNode
  action?: ReactNode
  loading?: boolean
  className?: string
  'data-testid'?: string
}

export function MetricCard({
  title,
  value,
  description,
  action,
  loading = false,
  className,
  ...props
}: MetricCardProps) {
  return (
    <Card className={cn('border-border bg-card shadow-xs', className)} {...props}>
      <CardHeader className="flex flex-row items-start justify-between gap-2 pb-1">
        <CardTitle className="text-xs uppercase text-muted-foreground font-medium tracking-wider">
          {title}
        </CardTitle>
        {action}
      </CardHeader>
      <CardContent className="space-y-1">
        {loading ? (
          <div role="status">
            <span className="sr-only">Загрузка: {title}</span>
            <Skeleton className="h-8 w-32" />
          </div>
        ) : (
          <div className="text-2xl font-bold tracking-tight text-foreground">{value}</div>
        )}
        {description && (
          <CardDescription className="text-xs">{description}</CardDescription>
        )}
      </CardContent>
    </Card>
  )
}
