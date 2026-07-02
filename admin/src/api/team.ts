import api from './client'
import type { User, Role, PermissionGroup, Paginated, StoreUserPayload } from '@/types'

export interface UserFilters {
  page?:     number
  per_page?: number
  q?:        string
  status?:   string
}

export const usersApi = {
  list: (params: UserFilters = {}) =>
    api.get<Paginated<User>>('/users', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ data: User }>(`/users/${id}`).then((r) => r.data.data),

  create: (payload: StoreUserPayload) =>
    api.post<{ data: User }>('/users', payload).then((r) => r.data.data),

  update: (id: number, payload: Partial<StoreUserPayload>) =>
    api.put<{ data: User }>(`/users/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/users/${id}`),

  syncRoles: (id: number, roleIds: number[]) =>
    api.put<{ data: User }>(`/users/${id}/roles`, { role_ids: roleIds }).then((r) => r.data.data),
}

export const rolesApi = {
  list: (params: { page?: number; per_page?: number } = {}) =>
    api.get<Paginated<Role>>('/roles', { params: { per_page: 100, ...params } }).then((r) => r.data),

  permissions: () =>
    api.get<{ data: PermissionGroup[] }>('/permissions').then((r) => r.data.data),

  create: (payload: { name: string; description?: string; permissions: string[] }) =>
    api.post<{ data: Role }>('/roles', payload).then((r) => r.data.data),

  update: (id: number, payload: { name?: string; description?: string; permissions?: string[] }) =>
    api.put<{ data: Role }>(`/roles/${id}`, payload).then((r) => r.data.data),

  destroy: (id: number) =>
    api.delete(`/roles/${id}`),
}
