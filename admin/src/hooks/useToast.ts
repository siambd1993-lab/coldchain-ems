import { useUiStore } from '@/stores/ui'
import type { Toast } from '@/stores/ui'
import type { ApiError } from '@/types'

export function useToast() {
  const addToast = useUiStore((s) => s.addToast)

  function toast(t: Omit<Toast, 'id'>) {
    addToast(t)
  }

  function success(title: string, message?: string) {
    addToast({ variant: 'success', title, message })
  }

  function error(title: string, message?: string) {
    addToast({ variant: 'error', title, message })
  }

  function info(title: string, message?: string) {
    addToast({ variant: 'info', title, message })
  }

  function warning(title: string, message?: string) {
    addToast({ variant: 'warning', title, message })
  }

  /**
   * Display an error from an API error envelope.
   */
  function apiError(err: unknown, fallback = 'An unexpected error occurred.') {
    const e = err as { response?: { data?: ApiError } }
    const apiMsg =
      e?.response?.data?.errors
        ? Object.values(e.response.data.errors).flat().join(' ')
        : e?.response?.data?.message

    addToast({ variant: 'error', title: 'Error', message: apiMsg ?? fallback })
  }

  return { toast, success, error, info, warning, apiError }
}
