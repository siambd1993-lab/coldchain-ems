import api from './client'
import type { Chamber, Paginated, StorageUnit, StoreStorageUnitPayload } from '@/types'

export interface ChamberFilters {
  page?:         number
  per_page?:     number
  status?:       string
  chamber_type?: string
}

export interface StorageUnitFilters {
  page?:            number
  per_page?:        number
  chamber_id?:      number
  status?:          string
  unit_type?:       string
  q?:               string
  /** Eager-load the parent chamber (name/code) onto each unit. */
  include_chamber?: boolean
}

export const chambersApi = {
  list: (params: ChamberFilters = {}) =>
    api.get<Paginated<Chamber>>('/chambers', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ data: Chamber }>(`/chambers/${id}`).then((r) => r.data.data),

  create: (payload: Record<string, unknown>) =>
    api.post<{ data: Chamber }>('/chambers', payload).then((r) => r.data.data),

  update: (id: number, payload: Record<string, unknown>) =>
    api.put<{ data: Chamber }>(`/chambers/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/chambers/${id}`),
}

export const storageUnitsApi = {
  list: (params: StorageUnitFilters = {}) =>
    api.get<Paginated<StorageUnit>>('/storage-units', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ data: StorageUnit }>(`/storage-units/${id}`).then((r) => r.data.data),

  create: (payload: StoreStorageUnitPayload) =>
    api.post<{ data: StorageUnit }>('/storage-units', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<StoreStorageUnitPayload>) =>
    api.put<{ data: StorageUnit }>(`/storage-units/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/storage-units/${id}`),
}
