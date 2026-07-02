import api from './client'
import type { LoginPayload, TokenBundle, User } from '@/types'

export const authApi = {
  login: (payload: LoginPayload) =>
    api.post<{ data: TokenBundle }>('/auth/login', payload).then((r) => r.data.data),

  refresh: (refreshToken: string) =>
    api
      .post<{ data: TokenBundle }>('/auth/refresh', { refresh_token: refreshToken })
      .then((r) => r.data.data),

  me: () =>
    api.get<{ data: User }>('/auth/me').then((r) => r.data.data),

  logout: (allDevices = false) =>
    api.post('/auth/logout', { all_devices: allDevices }),
}
