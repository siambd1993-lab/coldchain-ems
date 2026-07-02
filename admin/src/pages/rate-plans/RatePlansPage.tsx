import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, Trash2 } from 'lucide-react'
import { billingApi } from '@/api/billing'
import {
  Button, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, Badge, ConfirmDialog,
} from '@/components/ui'
import { formatMoney, formatDate } from '@/utils/format'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { RatePlanModal } from './RatePlanModal'
import type { RatePlan } from '@/types'

export function RatePlansPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()

  const [page, setPage]   = useState(1)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing,   setEditing]   = useState<RatePlan | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['rate-plans', { page }],
    queryFn:  () => billingApi.listRatePlans({ page }),
  })

  const destroy = useMutation({
    mutationFn: (id: number) => billingApi.destroyRatePlan(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rate-plans'] })
      success('Rate plan deleted')
    },
    onError: apiError,
  })

  async function handleDelete(rp: RatePlan) {
    const ok = await confirm({ title: `Delete "${rp.name}"?`, danger: true })
    if (ok) destroy.mutate(rp.id)
  }

  function handleEdit(rp: RatePlan) {
    setEditing(rp)
    setModalOpen(true)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Rate Plans</h1>
          <p className="text-sm text-gray-500">Storage billing rate configurations</p>
        </div>
        <Button size="sm" onClick={() => { setEditing(null); setModalOpen(true) }}>
          <Plus className="h-4 w-4" /> New Rate Plan
        </Button>
      </div>

      <Card>
        <CardHeader><CardTitle>All Rate Plans</CardTitle></CardHeader>
        <Table>
          <TableHead>
            <tr>
              <TableTh>Name</TableTh>
              <TableTh>Method</TableTh>
              <TableTh>Rate</TableTh>
              <TableTh>Tax Rate</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Created</TableTh>
              <TableTh />
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={7} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && <TableError colSpan={7} error={error} />}
            {!isLoading && !isError && data?.data.length === 0 && <TableEmpty colSpan={7} />}
            {data?.data.map((rp) => (
              <TableRow key={rp.id}>
                <TableTd>
                  <p className="font-medium text-gray-900">{rp.name}</p>
                </TableTd>
                <TableTd>
                  <Badge variant="blue">{rp.billing_method?.replace(/_/g, ' ')}</Badge>
                </TableTd>
                <TableTd className="tabular-nums">
                  {rp.billing_method === 'flat_monthly'
                    ? formatMoney(rp.rate_poisha)
                    : `${formatMoney(rp.rate_poisha)} / unit`}
                </TableTd>
                <TableTd className="text-gray-500">
                  {rp.tax_rate != null ? `${(Number(rp.tax_rate) * 100).toFixed(0)}%` : '—'}
                </TableTd>
                <TableTd>
                  <Badge variant={rp.is_active ? 'green' : 'default'}>
                    {rp.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </TableTd>
                <TableTd className="text-gray-400">{formatDate(rp.created_at)}</TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => handleEdit(rp)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="text-red-400 hover:text-red-600"
                      onClick={() => handleDelete(rp)}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
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

      <RatePlanModal open={modalOpen} ratePlan={editing} onClose={() => setModalOpen(false)} />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
