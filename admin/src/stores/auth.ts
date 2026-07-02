import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import type { User } from '@/types'

interface AuthState {
  user:         User | null
  accessToken:  string | null
  refreshToken: string | null
  isAuthenticated: boolean

  setAuth:    (user: User, accessToken: string, refreshToken: string) => void
  setTokens:  (accessToken: string, refreshToken: string) => void
  setUser:    (user: User) => void
  logout:     () => void

  // Permission helpers
  hasPermission: (permission: string) => boolean
  hasRole:       (role: string) => boolean
  isPlatformAdmin: () => boolean
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user:            null,
      accessToken:     null,
      refreshToken:    null,
      isAuthenticated: false,

      setAuth: (user, accessToken, refreshToken) =>
        set({ user, accessToken, refreshToken, isAuthenticated: true }),

      setTokens: (accessToken, refreshToken) =>
        set({ accessToken, refreshToken }),

      setUser: (user) =>
        set({ user }),

      logout: () =>
        set({ user: null, accessToken: null, refreshToken: null, isAuthenticated: false }),

      hasPermission: (permission) =>
        get().user?.permissions?.includes(permission) ?? false,

      hasRole: (role) =>
        get().user?.roles?.includes(role) ?? false,

      isPlatformAdmin: () =>
        get().user?.is_platform_admin === true,
    }),
    {
      name:    'coldchain-auth',
      storage: createJSONStorage(() => localStorage),
      // Only persist token pair; user is re-fetched on mount.
      partialize: (s) => ({
        accessToken:  s.accessToken,
        refreshToken: s.refreshToken,
        user:         s.user,
      }),
    },
  ),
)
