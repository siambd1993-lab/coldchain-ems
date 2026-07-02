import * as React from 'react'
import { cn } from '@/utils/cn'

export function Card({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('rounded-xl border border-gray-200 bg-white shadow-sm', className)}
      {...props}
    />
  )
}

export function CardHeader({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex items-center justify-between px-5 py-4 border-b border-gray-100', className)} {...props} />
}

export function CardTitle({ className, ...props }: React.HTMLAttributes<HTMLHeadingElement>) {
  return <h3 className={cn('text-base font-semibold text-gray-900', className)} {...props} />
}

export function CardContent({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('px-5 py-4', className)} {...props} />
}

export function CardFooter({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex items-center px-5 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl', className)} {...props} />
}

// ── Stat card ─────────────────────────────────────────────────────────────────

interface StatCardProps {
  label:       string
  value:       React.ReactNode
  icon?:       React.ReactNode
  trend?:      { value: number; label?: string }
  className?:  string
}

export function StatCard({ label, value, icon, trend, className }: StatCardProps) {
  return (
    <Card className={className}>
      <CardContent className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="text-sm text-gray-500 truncate">{label}</p>
          <p className="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{value}</p>
          {trend && (
            <p
              className={cn(
                'mt-1 text-xs font-medium',
                trend.value >= 0 ? 'text-green-600' : 'text-red-600',
              )}
            >
              {trend.value >= 0 ? '▲' : '▼'} {Math.abs(trend.value)}%
              {trend.label && <span className="ml-1 text-gray-400">{trend.label}</span>}
            </p>
          )}
        </div>
        {icon && (
          <div className="flex-shrink-0 rounded-lg bg-blue-50 p-2.5 text-blue-600">
            {icon}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
