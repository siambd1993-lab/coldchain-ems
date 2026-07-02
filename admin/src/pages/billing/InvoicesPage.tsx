import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, FileCheck, Ban, Trash2, Eye } from 'lucide-react'
import { billingApi } from '@/api/billing'
import {
  Button, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty,
  Pagination, InvoiceStatusBadge, ConfirmDialog,
} from '@/components/ui'
import { formatMoney, formatDate } from '@/utils/format'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { InvoiceModal }  from './InvoiceModal'
import { InvoiceDetail } from './InvoiceDetail'
import type { Invoice } from '@/types'

export function InvoicesPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()

  const [page, setPage]  = useState(1)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailInvoice, setDetailInvoice] = useState<Invoice | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', { page }],
    queryFn:  () => billingApi.listInvoices({ page, per_page: 20 }),
  })

  const issue = useMutation({
    mutationFn: (id: number) => billingApi.issueInvoice(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['invoices'] }); success('Invoice issued') },
    onError: apiError,
  })

  const voidInv = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      billingApi.voidInvoice(id, reason),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['invoices'] }); success('Invoice voided') },
    onError: apiError,
  })

  const destroy = useMutation({
    mutationFn: (id: number) => billingApi.destroyInvoice(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['invoices'] }); success('Invoice deleted') },
    onError: apiError,
  })

  async function handleIssue(inv: Invoice) {
    const ok = await confirm({ title: `Issue invoice ${inv.invoice_number}?`, message: 'This will send the invoice to the customer.' })
    if (ok) issue.mutate(inv.id)
  }

  async function handleVoid(inv: Invoice) {
    const ok = await confirm({
      title:   `Void ${inv.invoice_number}?`,
      message: 'This will reverse all charges and mark the invoice as void.',
      danger:  true,
    })
    if (ok) voidInv.mutate({ id: inv.id, reason: 'Voided via admin panel' })
  }

  async function handleDelete(inv: Invoice) {
    const ok = await confirm({
      title:   'Delete draft invoice?',
      message: 'Draft invoices only. This cannot be undone.',
      danger:  true,
    })
    if (ok) destroy.mutate(inv.id)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Invoices</h1>
          <p className="text-sm text-gray-500">Customer billing invoices</p>
        </div>
        <Button onClick={() => setModalOpen(true)} size="sm">
          <Plus className="h-4 w-4" /> New Invoice
        </Button>
      </div>

      <Card>
        <CardHeader><CardTitle>All Invoices</CardTitle></CardHeader>
        <Table>
          <TableHead>
            <tr>
              <TableTh>Invoice #</TableTh>
              <TableTh>Customer</TableTh>
              <TableTh>Issue Date</TableTh>
              <TableTh>Due Date</TableTh>
              <TableTh>Total</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Actions</TableTh>
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={7} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {!isLoading && data?.data.length === 0 && <TableEmpty colSpan={7} />}
            {data?.data.map((inv) => (
              <TableRow key={inv.id}>
                <TableTd className="font-mono text-xs font-medium">{inv.invoice_number}</TableTd>
                <TableTd>{inv.customer?.name ?? '—'}</TableTd>
                <TableTd className="text-gray-400">{formatDate(inv.issued_at ?? inv.issue_date)}</TableTd>
                <TableTd className="text-gray-400">{formatDate(inv.due_date)}</TableTd>
                <TableTd className="tabular-nums font-medium">{formatMoney(inv.total_poisha)}</TableTd>
                <TableTd><InvoiceStatusBadge status={inv.status} /></TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button
                      variant="ghost" size="icon"
                      onClick={() => setDetailInvoice(inv)}
                      title="View"
                    >
                      <Eye className="h-3.5 w-3.5" />
                    </Button>
                    {inv.status === 'draft' && (
                      <>
                        <Button variant="ghost" size="icon" title="Issue" onClick={() => handleIssue(inv)}>
                          <FileCheck className="h-3.5 w-3.5 text-green-600" />
                        </Button>
                        <Button
                          variant="ghost" size="icon"
                          className="text-red-400 hover:text-red-600"
                          onClick={() => handleDelete(inv)}
                          title="Delete"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </>
                    )}
                    {inv.status === 'issued' && (
                      <Button
                        variant="ghost" size="icon"
                        className="text-red-400 hover:text-red-600"
                        onClick={() => handleVoid(inv)}
                        title="Void"
                      >
                        <Ban className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </TableTd>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        {data?.meta && (
          <Pagination meta={data.meta} onPage={setPage} className="border-t border-gray-100 px-4" />
        )}
      </Card>

      <InvoiceModal open={modalOpen} onClose={() => setModalOpen(false)} />
      <InvoiceDetail
        invoice={detailInvoice}
        onClose={() => setDetailInvoice(null)}
      />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
