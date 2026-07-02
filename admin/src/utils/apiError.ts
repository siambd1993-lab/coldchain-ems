import type { ApiError } from '@/types'

/**
 * Human-readable message from any failed API call. Handles the API's error
 * envelope ({ error: { message, details } }), bare Laravel validation maps,
 * and network-level failures, in that order.
 */
export function apiErrorMessage(err: unknown, fallback = 'An unexpected error occurred.'): string {
  const e = err as { response?: { data?: ApiError }; message?: string }
  const data = e?.response?.data

  const detailMessages = data?.error?.details
    ? Object.values(data.error.details).flat().join(' ')
    : undefined

  const legacyErrors = data?.errors
    ? Object.values(data.errors).flat().join(' ')
    : undefined

  return (
    detailMessages ||
    data?.error?.message ||
    legacyErrors ||
    data?.message ||
    (e?.message === 'Network Error' ? 'Cannot reach the server. Check your connection.' : undefined) ||
    fallback
  )
}
