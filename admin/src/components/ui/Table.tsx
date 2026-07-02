import * as React from 'react'
import { cn } from '@/utils/cn'
import { apiErrorMessage } from '@/utils/apiError'

export function Table({ className, ...props }: React.HTMLAttributes<HTMLTableElement>) {
  return (
    <div className="w-full overflow-x-auto">
      <table className={cn('w-full text-sm', className)} {...props} />
    </div>
  )
}

export function TableHead({ className, ...props }: React.HTMLAttributes<HTMLTableSectionElement>) {
  return <thead className={cn('border-b border-gray-200 bg-gray-50/60', className)} {...props} />
}

export function TableBody({ className, ...props }: React.HTMLAttributes<HTMLTableSectionElement>) {
  return <tbody className={cn('divide-y divide-gray-100', className)} {...props} />
}

export function TableRow({ className, ...props }: React.HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr
      className={cn('transition-colors hover:bg-gray-50/70 data-[selected=true]:bg-blue-50', className)}
      {...props}
    />
  )
}

export function TableTh({ className, ...props }: React.ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th
      className={cn(
        'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500',
        className,
      )}
      {...props}
    />
  )
}

export function TableTd({ className, ...props }: React.TdHTMLAttributes<HTMLTableCellElement>) {
  return (
    <td className={cn('px-4 py-3 text-gray-700', className)} {...props} />
  )
}

// ── Empty state ───────────────────────────────────────────────────────────────

interface TableEmptyProps {
  colSpan: number
  message?: string
}

export function TableEmpty({ colSpan, message = 'No records found.' }: TableEmptyProps) {
  return (
    <tr>
      <td colSpan={colSpan} className="py-16 text-center text-sm text-gray-400">
        {message}
      </td>
    </tr>
  )
}

// ── Error state ───────────────────────────────────────────────────────────────

interface TableErrorProps {
  colSpan: number
  /** The error thrown by the query (any shape — parsed by apiErrorMessage). */
  error: unknown
}

export function TableError({ colSpan, error }: TableErrorProps) {
  return (
    <tr>
      <td colSpan={colSpan} className="py-16 text-center text-sm text-red-500">
        {apiErrorMessage(error, 'Could not load this list.')}
      </td>
    </tr>
  )
}
