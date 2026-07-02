import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { billingApi } from '@/api/billing'
import {
  Button, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, PaymentStatusBadge, Badge,
} from '@/components/ui'
import { formatMoney, formatDate, capitalize } from '@/utils/format'
import { PaymentModal }  from './PaymentModal'
import { AllocateModal } from './AllocateModal'
import type { Payment } from '@/types'

export function PaymentsPage() {
  const [page, setPage] = useState(1)
  const [paymentOpen,  setPaymentOpen]  = useState(false)
  const [allocateOpen, setAllocateOpen] = useState(false)
  const [selectedPayment, setSelectedPayment] = useState<Payment | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['payments', { page }],
    queryFn:  () => billingApi.listPayments({ page, per_page: 20 }),
  })

  function handleAllocate(p: Payment) {
    setSelectedPayment(p)
    setAllocateOpen(true)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Payments</h1>
          <p className="text-sm text-gray-500">Customer payment receipts</p>
        </div>
        <Button onClick={() => setPaymentOpen(true)} size="sm">
          <Plus className="h-4 w-4" /> Record Payment
        </Button>
      </div>

      <Card>
        <CardHeader><CardTitle>All Payments</CardTitle></CardHeader>
        <Table>
          <TableHead>
            <tr>
              <TableTh>Receipt #</TableTh>
              <TableTh>Customer</TableTh>
              <TableTh>Date</TableTh>
              <TableTh>Amount</TableTh>
              <TableTh>Unallocated</TableTh>
              <TableTh>Method</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Actions</TableTh>
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={8} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && <TableError colSpan={8} error={error} />}
            {!isLoading && !isError && data?.data.length === 0 && <TableEmpty colSpan={8} />}
            {data?.data.map((p) => (
              <TableRow key={p.id}>
                <TableTd className="font-mono text-xs">{p.payment_number}</TableTd>
                <TableTd>{p.customer?.name ?? '—'}</TableTd>
                <TableTd className="text-gray-400">{formatDate(p.paid_at)}</TableTd>
                <TableTd className="tabular-nums font-medium">{formatMoney(p.amount_poisha)}</TableTd>
                <TableTd className="tabular-nums text-gray-500">{formatMoney(p.unallocated_poisha)}</TableTd>
                <TableTd>
                  <Badge variant="default">{capitalize(p.method ?? '')}</Badge>
                </TableTd>
                <TableTd><PaymentStatusBadge status={p.status} /></TableTd>
                <TableTd>
                  {p.status === 'completed' && p.unallocated_poisha > 0 && (
                    <Button variant="outline" size="xs" onClick={() => handleAllocate(p)}>
                      Allocate
                    </Button>
                  )}
                </TableTd>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        {data?.meta && (
          <Pagination meta={data.meta} onPage={setPage} className="border-t border-gray-100 px-4" />
        )}
      </Card>

      <PaymentModal open={paymentOpen} onClose={() => setPaymentOpen(false)} />
      <AllocateModal
        open={allocateOpen}
        payment={selectedPayment}
        onClose={() => setAllocateOpen(false)}
      />
    </div>
  )
}
