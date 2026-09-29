'use client'

import { FilterBar, SelectField, TextField } from '@/src/shared/ui'
import type { SalesFilterOptions, SalesFilterParams } from '../api/sales-gateway'

interface SalesFiltersBarProps {
  filterOptions: SalesFilterOptions
  activeFilters: SalesFilterParams
  onFilterChange: (filters: SalesFilterParams) => void
}

export function SalesFiltersBar({
  filterOptions,
  activeFilters,
  onFilterChange,
}: SalesFiltersBarProps) {
  return (
    <FilterBar data-testid="sales-filters-bar" onReset={() => onFilterChange({})}>
      <TextField
        id="filter-date-from"
        label="Дата с"
        type="date"
        value={activeFilters.dateFrom ?? ''}
        min={filterOptions.min_date}
        max={filterOptions.max_date}
        onChange={(event) =>
          onFilterChange({ ...activeFilters, dateFrom: event.target.value || undefined })
        }
        containerClassName="min-w-[140px] w-auto gap-1.5"
        className="h-8 bg-muted/20 text-xs"
      />
      <TextField
        id="filter-date-to"
        label="Дата по"
        type="date"
        value={activeFilters.dateTo ?? ''}
        min={filterOptions.min_date}
        max={filterOptions.max_date}
        onChange={(event) =>
          onFilterChange({ ...activeFilters, dateTo: event.target.value || undefined })
        }
        containerClassName="min-w-[140px] w-auto gap-1.5"
        className="h-8 bg-muted/20 text-xs"
      />
      <SelectField
        id="filter-category"
        label="Категория"
        value={activeFilters.categoryId ?? ''}
        onValueChange={(value) =>
          onFilterChange({ ...activeFilters, categoryId: value || undefined })
        }
        options={[
          { value: '', label: 'Все категории' },
          ...filterOptions.categories.map((category) => ({
            value: category.id,
            label: category.name,
          })),
        ]}
        className="min-w-[160px] w-auto gap-1.5"
      />
      <SelectField
        id="filter-region"
        label="Регион"
        value={activeFilters.regionId ?? ''}
        onValueChange={(value) =>
          onFilterChange({ ...activeFilters, regionId: value || undefined })
        }
        options={[
          { value: '', label: 'Все регионы' },
          ...filterOptions.regions.map((region) => ({
            value: region.id,
            label: `${region.name} (${region.code})`,
          })),
        ]}
        className="min-w-[160px] w-auto gap-1.5"
      />
    </FilterBar>
  )
}
