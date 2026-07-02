import * as React from 'react'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/utils/cn'

const badgeVariants = cva(
  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
  {
    variants: {
      variant: {
        default:  'bg-gray-50  text-gray-700  ring-gray-200',
        blue:     'bg-blue-50  text-blue-700  ring-blue-200',
        green:    'bg-green-50 text-green-700 ring-green-200',
        yellow:   'bg-yellow-50 text-yellow-700 ring-yellow-200',
        red:      'bg-red-50   text-red-700   ring-red-200',
        purple:   'bg-purple-50 text-purple-700 ring-purple-200',
        orange:   'bg-orange-50 text-orange-700 ring-orange-200',
        cyan:     'bg-cyan-50  text-cyan-700  ring-cyan-200',
      },
    },
    defaultVariants: { variant: 'default' },
  },
)

export interface BadgeProps
  extends React.HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
  return <span className={cn(badgeVariants({ variant }), className)} {...props} />
}

// ── Status badge helpers ───────────────────────────────────────────────────────

type StatusMap = Record<string, VariantProps<typeof badgeVariants>['variant']>

const lotStatusMap: StatusMap = {
  in_storage:          'blue',
  partially_released:  'yellow',
  released:            'green',
}
const invoiceStatusMap: StatusMap = {
  draft:   'default',
  issued:  'blue',
  paid:    'green',
  void:    'red',
  partial: 'yellow',
}
const paymentStatusMap: StatusMap = {
  pending:   'yellow',
  completed: 'green',
  failed:    'red',
  refunded:  'orange',
}
const chamberStatusMap: StatusMap = {
  active:       'green',
  maintenance:  'yellow',
  offline:      'red',
}
const branchStatusMap: StatusMap = {
  active:            'green',
  inactive:          'default',
  under_maintenance: 'yellow',
}
const unitStatusMap: StatusMap = {
  available:   'green',
  occupied:    'blue',
  reserved:    'yellow',
  maintenance: 'orange',
}

function statusBadge(map: StatusMap, status: string) {
  return (
    <Badge variant={map[status] ?? 'default'}>
      {status.replace(/_/g, ' ')}
    </Badge>
  )
}

export const LotStatusBadge    = ({ status }: { status: string }) => statusBadge(lotStatusMap,     status)
export const InvoiceStatusBadge = ({ status }: { status: string }) => statusBadge(invoiceStatusMap, status)
export const PaymentStatusBadge = ({ status }: { status: string }) => statusBadge(paymentStatusMap, status)
export const ChamberStatusBadge = ({ status }: { status: string }) => statusBadge(chamberStatusMap, status)
export const UnitStatusBadge   = ({ status }: { status: string }) => statusBadge(unitStatusMap,    status)
export const BranchStatusBadge = ({ status }: { status: string }) => statusBadge(branchStatusMap,  status)
