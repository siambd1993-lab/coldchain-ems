/**
 * Format poisha (integer) to BDT display string.
 * 1 BDT = 100 poisha
 */
export function formatMoney(poisha: number | null | undefined, opts?: { compact?: boolean }): string {
  if (poisha == null) return '—'
  const taka = poisha / 100
  if (opts?.compact && Math.abs(taka) >= 1_000_000) {
    return `৳${(taka / 1_000_000).toFixed(2)}M`
  }
  if (opts?.compact && Math.abs(taka) >= 1_000) {
    return `৳${(taka / 1_000).toFixed(1)}K`
  }
  return new Intl.NumberFormat('en-BD', {
    style: 'currency',
    currency: 'BDT',
    currencyDisplay: 'symbol',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
    .format(taka)
    .replace('BDT', '৳')
    .trim()
}

/**
 * Format an ISO date string to a human-readable date.
 */
export function formatDate(
  value: string | Date | null | undefined,
  opts?: { time?: boolean; relative?: boolean },
): string {
  if (!value) return '—'
  const d = typeof value === 'string' ? new Date(value) : value
  if (isNaN(d.getTime())) return '—'

  if (opts?.relative) {
    const diffMs = Date.now() - d.getTime()
    const diffSec = Math.floor(diffMs / 1000)
    if (diffSec < 60) return 'just now'
    const diffMin = Math.floor(diffSec / 60)
    if (diffMin < 60) return `${diffMin}m ago`
    const diffHr = Math.floor(diffMin / 60)
    if (diffHr < 24) return `${diffHr}h ago`
    const diffDay = Math.floor(diffHr / 24)
    if (diffDay < 30) return `${diffDay}d ago`
  }

  if (opts?.time) {
    return new Intl.DateTimeFormat('en-BD', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(d)
  }

  return new Intl.DateTimeFormat('en-BD', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(d)
}

/**
 * Format a quantity (decimal string/number) with up to 3 decimal places.
 */
export function formatQuantity(
  value: string | number | null | undefined,
  unit?: string,
): string {
  if (value == null || value === '') return '—'
  const n = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(n)) return '—'
  const formatted = new Intl.NumberFormat('en-BD', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(n)
  return unit ? `${formatted} ${unit}` : formatted
}

/**
 * Convert a BDT decimal input to poisha integer for API submission.
 * e.g. "1500.50" → 150050
 */
export function takaToPoisha(taka: string | number): number {
  const n = typeof taka === 'string' ? parseFloat(taka) : taka
  if (isNaN(n)) return 0
  return Math.round(n * 100)
}

/**
 * Convert poisha integer to BDT decimal string for form inputs.
 */
export function poishaToTaka(poisha: number): string {
  return (poisha / 100).toFixed(2)
}

/**
 * Capitalize first letter of a string.
 */
export function capitalize(s: string): string {
  if (!s) return ''
  return s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ')
}

/**
 * Truncate a string with ellipsis.
 */
export function truncate(s: string, max: number): string {
  if (s.length <= max) return s
  return s.slice(0, max - 1) + '…'
}
