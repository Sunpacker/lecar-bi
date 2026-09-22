'use client'

import React from 'react'
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
  const handleDateFromChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onFilterChange({
      ...activeFilters,
      dateFrom: e.target.value || undefined,
    })
  }

  const handleDateToChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onFilterChange({
      ...activeFilters,
      dateTo: e.target.value || undefined,
    })
  }

  const handleCategoryChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onFilterChange({
      ...activeFilters,
      categoryId: e.target.value || undefined,
    })
  }

  const handleRegionChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    onFilterChange({
      ...activeFilters,
      regionId: e.target.value || undefined,
    })
  }

  const handleReset = () => {
    onFilterChange({})
  }

  return (
    <div className="filters-bar" data-testid="sales-filters-bar">
      <div className="filters-group">
        <label htmlFor="filter-date-from" className="filter-label">
          Дата с
        </label>
        <input
          id="filter-date-from"
          aria-label="Дата с"
          type="date"
          className="filter-input"
          value={activeFilters.dateFrom ?? ''}
          min={filterOptions.min_date}
          max={filterOptions.max_date}
          onChange={handleDateFromChange}
        />
      </div>

      <div className="filters-group">
        <label htmlFor="filter-date-to" className="filter-label">
          Дата по
        </label>
        <input
          id="filter-date-to"
          aria-label="Дата по"
          type="date"
          className="filter-input"
          value={activeFilters.dateTo ?? ''}
          min={filterOptions.min_date}
          max={filterOptions.max_date}
          onChange={handleDateToChange}
        />
      </div>

      <div className="filters-group">
        <label htmlFor="filter-category" className="filter-label">
          Категория
        </label>
        <select
          id="filter-category"
          aria-label="Категория"
          className="filter-select"
          value={activeFilters.categoryId ?? ''}
          onChange={handleCategoryChange}
        >
          <option value="">Все категории</option>
          {filterOptions.categories.map((cat) => (
            <option key={cat.id} value={cat.id}>
              {cat.name}
            </option>
          ))}
        </select>
      </div>

      <div className="filters-group">
        <label htmlFor="filter-region" className="filter-label">
          Регион
        </label>
        <select
          id="filter-region"
          aria-label="Регион"
          className="filter-select"
          value={activeFilters.regionId ?? ''}
          onChange={handleRegionChange}
        >
          <option value="">Все регионы</option>
          {filterOptions.regions.map((reg) => (
            <option key={reg.id} value={reg.id}>
              {reg.name} ({reg.code})
            </option>
          ))}
        </select>
      </div>

      <button type="button" className="filter-reset-button" onClick={handleReset}>
        Сбросить
      </button>
    </div>
  )
}
