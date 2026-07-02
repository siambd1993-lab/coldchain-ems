import api from './client'
import type { Branch, Paginated, StoreBranchPayload } from '@/types'

export interface BranchFilters {
  page?:     number
  per_page?: number
  status?:   string
  q?:        string
}

export const branchesApi = {
  list: (params: BranchFilters = {}) =>
    api.get<Paginated<Branch>>('/branches', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ data: Branch }>(`/branches/${id}`).then((r) => r.data.data),

  create: (payload: StoreBranchPayload) =>
    api.post<{ data: Branch }>('/branches', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<StoreBranchPayload>) =>
    api.put<{ data: Branch }>(`/branches/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/branches/${id}`),
}
