import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1'

export const apiClient = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  timeout: 30_000,
})

// ── Request interceptor — inject auth + branch headers ───────────────────────

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token    = useAuthStore.getState().accessToken
  const branchId = useUiStore.getState().activeBranchId

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  if (branchId !== null) {
    config.headers['X-Branch-Id'] = String(branchId)
  }

  return config
})

// ── Response interceptor — transparent token refresh on 401 ─────────────────

let refreshing: Promise<string> | null = null

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const original = error.config!

    // Skip retry for auth endpoints to avoid infinite loops.
    const isAuthUrl = original.url?.includes('/auth/')
    if (error.response?.status !== 401 || isAuthUrl || (original as any)._retry) {
      return Promise.reject(error)
    }

    // Coalesce concurrent 401s into a single refresh call.
    if (!refreshing) {
      const { refreshToken, setTokens, logout } = useAuthStore.getState()

      if (!refreshToken) {
        logout()
        return Promise.reject(error)
      }

      refreshing = apiClient
        .post<{ data: { access_token: string; refresh_token: string } }>(
          '/auth/refresh',
          { refresh_token: refreshToken },
        )
        .then((res) => {
          const { access_token, refresh_token } = res.data.data
          setTokens(access_token, refresh_token)
          return access_token
        })
        .catch((refreshError) => {
          useAuthStore.getState().logout()
          return Promise.reject(refreshError)
        })
        .finally(() => {
          refreshing = null
        })
    }

    try {
      const newToken = await refreshing
      ;(original as any)._retry = true
      original.headers.Authorization = `Bearer ${newToken}`
      return apiClient(original)
    } catch {
      return Promise.reject(error)
    }
  },
)

export default apiClient
