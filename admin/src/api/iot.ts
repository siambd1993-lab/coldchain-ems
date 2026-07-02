import api from './client'
import type { Device, AlertRow, EnergySummary, EnergyLive, EnergyInsights, Paginated } from '@/types'

export interface DeviceFilters {
  page?:        number
  per_page?:    number
  q?:           string
  status?:      string
  device_type?: string
  chamber_id?:  number
}

export interface StoreDevicePayload {
  device_uid:  string
  name:        string
  device_type: string
  protocol?:   string
  branch_id:   number
  chamber_id?: number | null
  model?:      string
  manufacturer?: string
}

export const devicesApi = {
  list: (params: DeviceFilters = {}) =>
    api.get<Paginated<Device>>('/devices', { params }).then((r) => r.data),

  create: (payload: StoreDevicePayload) =>
    api.post<{ data: Device }>('/devices', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<StoreDevicePayload> & { status?: string }) =>
    api.put<{ data: Device }>(`/devices/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/devices/${id}`),
}

export interface AlertFilters {
  page?:      number
  per_page?:  number
  status?:    string
  severity?:  string
}

export const alertsApi = {
  list: (params: AlertFilters = {}) =>
    api.get<Paginated<AlertRow>>('/alerts', { params }).then((r) => r.data),

  acknowledge: (id: number) =>
    api.post<{ data: AlertRow }>(`/alerts/${id}/acknowledge`).then((r) => r.data.data),

  resolve: (id: number, note?: string) =>
    api.post<{ data: AlertRow }>(`/alerts/${id}/resolve`, { resolution_note: note }).then((r) => r.data.data),
}

export const energyApi = {
  summary: (params: { from?: string; to?: string } = {}) =>
    api.get<{ data: EnergySummary }>('/energy/summary', { params }).then((r) => r.data.data),

  live: () =>
    api.get<{ data: EnergyLive }>('/energy/live').then((r) => r.data.data),

  insights: () =>
    api.get<{ data: EnergyInsights }>('/energy/insights').then((r) => r.data.data),
}
