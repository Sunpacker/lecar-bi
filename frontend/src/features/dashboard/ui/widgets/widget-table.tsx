import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { WidgetTableData } from '../../model/widget-data-loader'

interface WidgetTableProps {
  title: string
  data: WidgetTableData
}

export function WidgetTable({ title, data }: WidgetTableProps) {
  return (
    <Card className="h-full flex flex-col border-border bg-card/60 shadow-xs overflow-hidden">
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 p-0 overflow-auto">
        <Table>
          <TableHeader>
            <TableRow>
              {data.columns.map((col) => (
                <TableHead key={col.key} className="text-xs">
                  {col.label}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.rows.map((row, idx) => (
              <TableRow key={idx}>
                {data.columns.map((col) => (
                  <TableCell key={col.key} className="text-xs">
                    {String(row[col.key] ?? '')}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}
