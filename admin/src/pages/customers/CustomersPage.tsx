import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil, Trash2 } from 'lucide-react'
import { customersApi } from '@/api/customers'
import {
  Button, Input, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, ConfirmDialog, Badge,
} from '@/components/ui'
import { formatMoney, formatDate } from '@/utils/format'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { CustomerModal } from './CustomerModal'
import type { Customer } from '@/types'

export function CustomersPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()

  const [page,   setPage]   = useState(1)
  const [q,      setQ]      = useState('')
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editing,   setEditing]   = useState<Customer | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['customers', { page, q: search }],
    queryFn:  () => customersApi.list({ page, per_page: 20, q: search }),
  })

  const destroy = useMutation({
    mutationFn: (id: number) => customersApi.destroy(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customers'] })
      success('Customer deleted')
    },
    onError: apiError,
  })

  async function handleDelete(c: Customer) {
    const ok = await confirm({
      title:   `Delete ${c.name}?`,
      message: 'This will permanently delete the customer and cannot be undone.',
      danger:  true,
    })
    if (ok) destroy.mutate(c.id)
  }

  function handleEdit(c: Customer) {
    setEditing(c)
    setModalOpen(true)
  }

  function handleNew() {
    setEditing(null)
    setModalOpen(true)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Customers</h1>
          <p className="text-sm text-gray-500">Manage your cold-storage customers</p>
        </div>
        <Button onClick={handleNew} size="sm">
          <Plus className="h-4 w-4" /> New Customer
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Customers</CardTitle>
          <form
            onSubmit={(e) => { e.preventDefault(); setSearch(q); setPage(1) }}
            className="flex items-center gap-2"
          >
            <Input
              placeholder="Search by name…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              prefix={<Search className="h-3.5 w-3.5" />}
              className="w-52"
            />
            <Button type="submit" size="sm" variant="outline">Search</Button>
          </form>
        </CardHeader>

        <Table>
          <TableHead>
            <tr>
              <TableTh>Name</TableTh>
              <TableTh>Type</TableTh>
              <TableTh>Phone</TableTh>
              <TableTh>Balance</TableTh>
              <TableTh>Credit Limit</TableTh>
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
            {data?.data.map((c) => (
              <TableRow key={c.id}>
                <TableTd>
                  <div>
                    <p className="font-medium text-gray-900">{c.name}</p>
                    <p className="text-xs text-gray-400">{c.email}</p>
                  </div>
                </TableTd>
                <TableTd>
                  <Badge variant={c.type === 'business' ? 'blue' : 'default'}>
                    {c.type ?? '—'}
                  </Badge>
                </TableTd>
                <TableTd className="text-gray-500">{c.phone ?? '—'}</TableTd>
                <TableTd>
                  <span className={c.balance_poisha > 0 ? 'text-red-600 font-medium' : ''}>
                    {formatMoney(c.balance_poisha)}
                  </span>
                </TableTd>
                <TableTd>{formatMoney(c.credit_limit_poisha)}</TableTd>
                <TableTd className="text-gray-400">{formatDate(c.created_at)}</TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => handleEdit(c)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="text-red-400 hover:text-red-600"
                      onClick={() => handleDelete(c)}
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
          <Pagination
            meta={data.meta}
            onPage={(p) => { setPage(p) }}
            className="border-t border-gray-100 px-4"
          />
        )}
      </Card>

      <CustomerModal
        open={modalOpen}
        customer={editing}
        onClose={() => setModalOpen(false)}
      />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
