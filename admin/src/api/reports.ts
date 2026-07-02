import api from './client'
import type {
  OccupancyRow, RevenueReport, ReceivablesReport, StockReport,
  AuditLogRow, Paginated,
} from '@/types'

export interface DateRange {
  from?: string
  to?:   string
}

export const reportsApi = {
  occupancy: () =>
    api.get<{ data: OccupancyRow[] }>('/reports/occupancy').then((r) => r.data.data),

  revenue: (params: DateRange = {}) =>
    api.get<{ data: RevenueReport }>('/reports/revenue', { params }).then((r) => r.data.data),

  receivables: () =>
    api.get<{ data: ReceivablesReport }>('/reports/receivables').then((r) => r.data.data),

  stock: (params: { expiring_days?: number } = {}) =>
    api.get<{ data: StockReport }>('/reports/stock', { params }).then((r) => r.data.data),
}

export interface AuditFilters {
  page?:     number
  per_page?: number
  q?:        string
  action?:   string
  actor?:    string
  from?:     string
  to?:       string
}

export const auditApi = {
  list: (params: AuditFilters = {}) =>
    api.get<Paginated<AuditLogRow>>('/audit-logs', { params }).then((r) => r.data),
}
