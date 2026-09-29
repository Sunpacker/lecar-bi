import type { ReactNode } from 'react'
import { RotateCcwIcon } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

export interface FilterBarProps {
  children: ReactNode
  actions?: ReactNode
  onReset?: () => void
  disabled?: boolean
  className?: string
  'data-testid'?: string
}

export function FilterBar({
  children,
  actions,
  onReset,
  disabled = false,
  className,
  ...props
}: FilterBarProps) {
  return (
    <Card className={cn('p-4 border-border bg-card shadow-xs', className)} {...props}>
      <fieldset disabled={disabled} className="flex min-w-0 flex-wrap items-end gap-3.5">
        {children}
        {(actions || onReset) && (
          <div className="ml-auto flex flex-wrap items-center gap-2">
            {actions}
            {onReset && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-8 gap-1.5 text-xs text-muted-foreground"
                onClick={onReset}
                disabled={disabled}
              >
                <RotateCcwIcon className="size-3.5" />
                Сбросить
              </Button>
            )}
          </div>
        )}
      </fieldset>
    </Card>
  )
}
