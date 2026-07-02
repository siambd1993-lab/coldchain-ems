import type { ElementType } from 'react'
import { CheckCircle2, AlertCircle, Info, TriangleAlert, X } from 'lucide-react'
import { useUiStore } from '@/stores/ui'
import { cn } from '@/utils/cn'
import type { Toast } from '@/stores/ui'

const variantMap: Record<
  Toast['variant'],
  { icon: ElementType; cls: string; iconCls: string }
> = {
  success: { icon: CheckCircle2,  cls: 'bg-white border-green-200',  iconCls: 'text-green-500' },
  error:   { icon: AlertCircle,   cls: 'bg-white border-red-200',    iconCls: 'text-red-500'   },
  info:    { icon: Info,          cls: 'bg-white border-blue-200',   iconCls: 'text-blue-500'  },
  warning: { icon: TriangleAlert, cls: 'bg-white border-yellow-200', iconCls: 'text-yellow-500'},
}

export function Toaster() {
  const { toasts, removeToast } = useUiStore()

  if (toasts.length === 0) return null

  return (
    <div
      aria-live="assertive"
      className="pointer-events-none fixed bottom-4 right-4 z-[9999] flex flex-col gap-2"
    >
      {toasts.map((t) => {
        const { icon: Icon, cls, iconCls } = variantMap[t.variant]
        return (
          <div
            key={t.id}
            className={cn(
              'pointer-events-auto flex w-80 items-start gap-3 rounded-xl border p-4 shadow-lg',
              cls,
            )}
          >
            <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', iconCls)} />
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-gray-900">{t.title}</p>
              {t.message && <p className="mt-0.5 text-xs text-gray-500">{t.message}</p>}
            </div>
            <button
              onClick={() => removeToast(t.id)}
              className="shrink-0 rounded p-0.5 text-gray-400 hover:text-gray-700"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )
      })}
    </div>
  )
}
