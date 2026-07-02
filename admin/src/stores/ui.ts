import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import type { Branch } from '@/types'

interface UiState {
  // Sidebar
  sidebarOpen: boolean
  toggleSidebar: () => void
  setSidebarOpen: (open: boolean) => void

  // Active branch (X-Branch-Id header)
  activeBranchId:   number | null
  activeBranchName: string | null
  availableBranches: Branch[]

  setActiveBranch:    (branch: Branch) => void
  setAvailableBranches: (branches: Branch[]) => void
  clearBranch:        () => void

  // Toast notifications (managed here for global access)
  toasts: Toast[]
  addToast:    (toast: Omit<Toast, 'id'>) => void
  removeToast: (id: string) => void
}

export interface Toast {
  id:       string
  title:    string
  message?: string
  variant:  'success' | 'error' | 'info' | 'warning'
  duration?: number
}

let toastId = 0

export const useUiStore = create<UiState>()(
  persist(
    (set) => ({
      sidebarOpen: true,
      toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),
      setSidebarOpen: (open) => set({ sidebarOpen: open }),

      activeBranchId:    null,
      activeBranchName:  null,
      availableBranches: [],

      setActiveBranch: (branch) =>
        set({ activeBranchId: branch.id, activeBranchName: branch.name }),

      setAvailableBranches: (branches) =>
        set({ availableBranches: branches }),

      clearBranch: () =>
        set({ activeBranchId: null, activeBranchName: null }),

      toasts: [],

      addToast: (toast) => {
        const id = String(++toastId)
        set((s) => ({ toasts: [...s.toasts, { ...toast, id }] }))
        const duration = toast.duration ?? 4_000
        if (duration > 0) {
          setTimeout(
            () => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
            duration,
          )
        }
      },

      removeToast: (id) =>
        set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
    }),
    {
      name:    'coldchain-ui',
      storage: createJSONStorage(() => localStorage),
      partialize: (s) => ({
        sidebarOpen:      s.sidebarOpen,
        activeBranchId:   s.activeBranchId,
        activeBranchName: s.activeBranchName,
      }),
    },
  ),
)
