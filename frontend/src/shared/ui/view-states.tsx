import type { ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'

interface StateProps {
  title: string
  description?: string
  actionLabel?: string
  onAction?: () => void
  className?: string
  'data-testid'?: string
}

function StateCard({
  title,
  description,
  actionLabel,
  onAction,
  className,
  ...props
}: StateProps) {
  return (
    <Card
      className={cn(
        'border-dashed border-border bg-card/50 p-8 text-center shadow-xs',
        className,
      )}
      {...props}
    >
      <CardHeader className="pb-2">
        <CardTitle className="text-base font-semibold">{title}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {description && <CardDescription>{description}</CardDescription>}
        {actionLabel && onAction && (
          <Button type="button" variant="outline" size="sm" onClick={onAction}>
            {actionLabel}
          </Button>
        )}
      </CardContent>
    </Card>
  )
}

export function EmptyState(props: StateProps) {
  return <StateCard {...props} />
}
export function ErrorState(props: StateProps) {
  return (
    <StateCard
      {...props}
      className={cn(
        'border-destructive/40 bg-destructive/5 text-red-700 dark:text-destructive [&_[data-slot=card-description]]:text-foreground',
        props.className,
      )}
    />
  )
}
export interface LoadingStateProps {
  description: string
  skeleton?: ReactNode
  className?: string
  'data-testid'?: string
}
export function LoadingState({
  description,
  skeleton,
  className,
  ...props
}: LoadingStateProps) {
  return (
    <div
      role="status"
      aria-label={description}
      className={cn('space-y-4 py-2', className)}
      {...props}
    >
      <span className="sr-only">{description}</span>
      {skeleton ?? (
        <>
          <Skeleton className="h-5 w-44" />
          <Skeleton className="h-28 w-full" />
          <Skeleton className="h-56 w-full" />
        </>
      )}
    </div>
  )
}
