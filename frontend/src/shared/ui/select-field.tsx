'use client'

import { useId } from 'react'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { cn } from '@/lib/utils'

export interface SelectFieldOption {
  value: string
  label: string
  disabled?: boolean
}
export interface SelectFieldProps {
  label: string
  options: readonly SelectFieldOption[]
  value: string
  onValueChange: (value: string) => void
  placeholder?: string
  hint?: string
  error?: string
  disabled?: boolean
  id?: string
  className?: string
}

export function SelectField({
  label,
  options,
  value,
  onValueChange,
  placeholder = 'Выберите значение',
  hint,
  error,
  disabled,
  id,
  className,
}: SelectFieldProps) {
  const generatedId = useId()
  const selectId = id ?? generatedId
  const hintId = hint ? `${selectId}-hint` : undefined
  const errorId = error ? `${selectId}-error` : undefined

  return (
    <Field data-invalid={!!error} className={cn('min-w-0', className)}>
      <FieldLabel htmlFor={selectId}>{label}</FieldLabel>
      <Select
        items={options}
        value={value}
        onValueChange={(next) => onValueChange(next ?? '')}
        disabled={disabled}
      >
        <SelectTrigger
          id={selectId}
          aria-label={label}
          aria-invalid={!!error || undefined}
          aria-describedby={[hintId, errorId].filter(Boolean).join(' ') || undefined}
          className="w-full min-w-0"
        >
          <SelectValue
            placeholder={
              options.find((option) => option.value === '')?.label ?? placeholder
            }
          />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem
              key={option.value}
              value={option.value}
              disabled={option.disabled}
            >
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {hint && <FieldDescription id={hintId}>{hint}</FieldDescription>}
      {error && <FieldError id={errorId}>{error}</FieldError>}
    </Field>
  )
}
