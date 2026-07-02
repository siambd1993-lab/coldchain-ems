import api from './client'
import type { Product, Paginated, StoreProductPayload } from '@/types'

export interface ProductFilters {
  page?:     number
  per_page?: number
  q?:        string
  category?: string
}

export const productsApi = {
  list: (params: ProductFilters = {}) =>
    api.get<Paginated<Product>>('/products', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ data: Product }>(`/products/${id}`).then((r) => r.data.data),

  create: (payload: StoreProductPayload) =>
    api.post<{ data: Product }>('/products', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<StoreProductPayload>) =>
    api.put<{ data: Product }>(`/products/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/products/${id}`),
}
