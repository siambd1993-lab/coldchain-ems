import type { ReactNode } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/utils/cn'
import type { PaginationMeta } from '@/types'

interface PaginationProps {
  meta:       PaginationMeta
  onPage:     (page: number) => void
  className?: string
}

export function Pagination({ meta, onPage, className }: PaginationProps) {
  const { current_page, last_page, from, to, total } = meta

  if (last_page <= 1) return null

  const pages: (number | 'ellipsis')[] = []
  const delta = 2
  const left  = Math.max(1, current_page - delta)
  const right = Math.min(last_page, current_page + delta)

  if (left > 1) {
    pages.push(1)
    if (left > 2) pages.push('ellipsis')
  }
  for (let i = left; i <= right; i++) pages.push(i)
  if (right < last_page) {
    if (right < last_page - 1) pages.push('ellipsis')
    pages.push(last_page)
  }

  return (
    <div className={cn('flex items-center justify-between gap-4 px-1 py-3', className)}>
      <p className="text-sm text-gray-500">
        Showing <span className="font-medium text-gray-700">{from}–{to}</span> of{' '}
        <span className="font-medium text-gray-700">{total}</span>
      </p>
      <nav className="flex items-center gap-1" aria-label="Pagination">
        <PageBtn
          onClick={() => onPage(current_page - 1)}
          disabled={current_page === 1}
          aria-label="Previous page"
        >
          <ChevronLeft className="h-4 w-4" />
        </PageBtn>

        {pages.map((p, i) =>
          p === 'ellipsis' ? (
            <span key={`ellipsis-${i}`} className="px-2 text-gray-400">…</span>
          ) : (
            <PageBtn
              key={p}
              onClick={() => onPage(p as number)}
              active={p === current_page}
            >
              {p}
            </PageBtn>
          ),
        )}

        <PageBtn
          onClick={() => onPage(current_page + 1)}
          disabled={current_page === last_page}
          aria-label="Next page"
        >
          <ChevronRight className="h-4 w-4" />
        </PageBtn>
      </nav>
    </div>
  )
}

function PageBtn({
  onClick,
  disabled,
  active,
  children,
  ...props
}: {
  onClick:    () => void
  disabled?:  boolean
  active?:    boolean
  children:   ReactNode
  'aria-label'?: string
}) {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-sm font-medium transition-colors',
        'disabled:pointer-events-none disabled:opacity-40',
        active
          ? 'bg-blue-600 text-white'
          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
      )}
      {...props}
    >
      {children}
    </button>
  )
}
