import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { billingApi } from '@/api/billing'
import { Modal, ModalFooter, Button, Input, Alert } from '@/components/ui'
import { formatMoney, takaToPoisha } from '@/utils/format'
import { useToast } from '@/hooks/useToast'
import type { Payment } from '@/types'

interface Props {
  open:    boolean
  payment: Payment | null
  onClose: () => void
}

export function AllocateModal({ open, payment, onClose }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const [allocations, setAllocations] = useState<Record<number, string>>({})

  const { data: invoices } = useQuery({
    queryKey: ['invoices-open', payment?.customer?.id],
    queryFn:  () =>
      billingApi.listInvoices({
        customer_id: payment!.customer?.id,
        status:      'issued',
        per_page:    50,
      }),
    enabled: open && !!payment,
  })

  const allocate = useMutation({
    mutationFn: () => {
      const allocs = Object.entries(allocations)
        .filter(([, v]) => v && Number(v) > 0)
        .map(([invoice_id, taka]) => ({
          invoice_id:    Number(invoice_id),
          amount_poisha: takaToPoisha(taka),
        }))
      return billingApi.allocatePayment(payment!.id, allocs)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['payments'] })
      qc.invalidateQueries({ queryKey: ['invoices'] })
      success('Allocations saved')
      setAllocations({})
      onClose()
    },
    onError: apiError,
  })

  const available   = payment?.unallocated_poisha ?? 0
  const totalAlloc  = Object.values(allocations).reduce((s, v) => s + (takaToPoisha(v) || 0), 0)
  const remaining   = available - totalAlloc

  if (!payment) return null

  return (
    <Modal open={open} onClose={onClose} title="Allocate Payment" size="lg">
      <div className="space-y-4">
        <div className="rounded-lg bg-gray-50 p-3 text-sm">
          <p className="text-gray-500">Receipt: <strong>{payment.payment_number}</strong></p>
          <p className="text-gray-500">
            Unallocated: <strong className="text-green-700">{formatMoney(available)}</strong>
          </p>
        </div>

        {remaining < 0 && (
          <Alert variant="error">
            Total allocated exceeds available by {formatMoney(Math.abs(remaining))}
          </Alert>
        )}

        <div className="space-y-2">
          {(invoices?.data ?? []).length === 0 && (
            <p className="py-4 text-center text-sm text-gray-400">No outstanding invoices for this customer.</p>
          )}
          {invoices?.data.map((inv) => (
            <div key={inv.id} className="flex items-center justify-between gap-4 rounded-lg border border-gray-100 p-3">
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-gray-900">{inv.invoice_number}</p>
                <p className="text-xs text-gray-400">
                  Outstanding: {formatMoney(inv.amount_due_poisha)}
                </p>
              </div>
              <Input
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                prefix="৳"
                className="w-36"
                value={allocations[inv.id] ?? ''}
                onChange={(e) =>
                  setAllocations((a) => ({ ...a, [inv.id]: e.target.value }))
                }
              />
            </div>
          ))}
        </div>

        {totalAlloc > 0 && (
          <div className="flex justify-end text-sm">
            <p className="text-gray-600">
              Allocating: <strong>{formatMoney(totalAlloc)}</strong> |
              Remaining: <strong className={remaining < 0 ? 'text-red-600' : ''}>{formatMoney(remaining)}</strong>
            </p>
          </div>
        )}
      </div>
      <ModalFooter className="-mx-5 mt-3">
        <Button variant="outline" size="sm" onClick={onClose}>Cancel</Button>
        <Button
          size="sm"
          onClick={() => allocate.mutate()}
          loading={allocate.isPending}
          disabled={totalAlloc === 0 || remaining < 0}
        >
          Save Allocations
        </Button>
      </ModalFooter>
    </Modal>
  )
}
