import type { ReactNode } from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { ChartContainer, type ChartConfig } from '@/components/ui/chart'
import { cn } from '@/lib/utils'

export interface ChartCardProps {
  title: string
  description?: string
  actions?: ReactNode
  config: ChartConfig
  children: ReactNode
  className?: string
  'data-testid'?: string
}

export function ChartCard({
  title,
  description,
  actions,
  config,
  children,
  className,
  ...props
}: ChartCardProps) {
  return (
    <Card className={cn('border-border bg-card shadow-xs', className)} {...props}>
      <CardHeader className="flex flex-col gap-3 pb-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <CardTitle className="text-base font-semibold text-foreground">
            {title}
          </CardTitle>
          {description && (
            <CardDescription className="mt-1 text-xs">{description}</CardDescription>
          )}
        </div>
        {actions}
      </CardHeader>
      <CardContent>
        <ChartContainer config={config} className="h-[260px] w-full min-w-0 aspect-auto">
          {children}
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
