import api from './client'
import type {
  Paginated,
  StockLot,
  StockMovement,
  IntakePayload,
  ReleasePayload,
  AdjustPayload,
  Product,
} from '@/types'

export interface LotFilters {
  page?:       number
  per_page?:   number
  customer_id?: number
  status?:     string
  chamber_id?: number
  from?:       string
  to?:         string
}

export const inventoryApi = {
  // ── Products ──────────────────────────────────────────────────────────
  listProducts: (params: { page?: number; per_page?: number; q?: string } = {}) =>
    api.get<Paginated<Product>>('/products', { params }).then((r) => r.data),

  createProduct: (payload: Record<string, unknown>) =>
    api.post<{ data: Product }>('/products', payload).then((r) => r.data.data),

  // ── Stock Lots ────────────────────────────────────────────────────────
  listLots: (params: LotFilters = {}) =>
    api.get<Paginated<StockLot>>('/stock-lots', { params }).then((r) => r.data),

  showLot: (id: number) =>
    api.get<{ data: StockLot }>(`/stock-lots/${id}`).then((r) => r.data.data),

  movements: (lotId: number, params: { page?: number; per_page?: number } = {}) =>
    api
      .get<Paginated<StockMovement>>(`/stock-lots/${lotId}/movements`, { params })
      .then((r) => r.data),

  intake: (payload: IntakePayload) =>
    api.post<{ data: StockLot }>('/stock-lots', payload).then((r) => r.data.data),

  release: (lotId: number, payload: ReleasePayload) =>
    api
      .post<{ data: StockMovement }>(`/stock-lots/${lotId}/release`, payload)
      .then((r) => r.data.data),

  adjust: (lotId: number, payload: AdjustPayload) =>
    api
      .post<{ data: StockMovement }>(`/stock-lots/${lotId}/adjust`, payload)
      .then((r) => r.data.data),

  transfer: (lotId: number, payload: { to_chamber_id: number; to_storage_unit_id?: number; reason?: string }) =>
    api
      .post<{ data: StockMovement }>(`/stock-lots/${lotId}/transfer`, payload)
      .then((r) => r.data.data),
}
