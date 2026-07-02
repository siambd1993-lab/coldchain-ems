import type { ElementType, ReactNode } from 'react'
import { AlertCircle, CheckCircle2, Info, TriangleAlert, X } from 'lucide-react'
import { cn } from '@/utils/cn'

type AlertVariant = 'info' | 'success' | 'warning' | 'error'

const variantMap: Record<
  AlertVariant,
  { icon: ElementType; cls: string; iconCls: string }
> = {
  info:    { icon: Info,          cls: 'bg-blue-50   border-blue-200',  iconCls: 'text-blue-500'  },
  success: { icon: CheckCircle2,  cls: 'bg-green-50  border-green-200', iconCls: 'text-green-500' },
  warning: { icon: TriangleAlert, cls: 'bg-yellow-50 border-yellow-200', iconCls: 'text-yellow-500' },
  error:   { icon: AlertCircle,   cls: 'bg-red-50    border-red-200',   iconCls: 'text-red-500'   },
}

interface AlertProps {
  variant?:    AlertVariant
  title?:      string
  children?:   ReactNode
  onDismiss?:  () => void
  className?:  string
}

export function Alert({
  variant = 'info',
  title,
  children,
  onDismiss,
  className,
}: AlertProps) {
  const { icon: Icon, cls, iconCls } = variantMap[variant]
  return (
    <div
      role="alert"
      className={cn('flex gap-3 rounded-lg border p-4', cls, className)}
    >
      <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', iconCls)} />
      <div className="min-w-0 flex-1 text-sm">
        {title && <p className="font-semibold text-gray-800">{title}</p>}
        {children && <div className="mt-0.5 text-gray-600">{children}</div>}
      </div>
      {onDismiss && (
        <button
          onClick={onDismiss}
          className="shrink-0 rounded p-0.5 text-gray-400 hover:text-gray-600"
        >
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  )
}
