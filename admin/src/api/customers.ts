import api from './client'
import type { Customer, Paginated, StoreCustomerPayload } from '@/types'

export interface CustomerFilters {
  page?:     number
  per_page?: number
  q?:        string
  status?:   string
  type?:     string
}

export const customersApi = {
  list: (params: CustomerFilters = {}) =>
    api.get<Paginated<Customer>>('/customers', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ data: Customer }>(`/customers/${id}`).then((r) => r.data.data),

  create: (payload: StoreCustomerPayload) =>
    api.post<{ data: Customer }>('/customers', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<StoreCustomerPayload>) =>
    api.put<{ data: Customer }>(`/customers/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/customers/${id}`),
}
