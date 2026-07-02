import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil, Trash2, Building2 } from 'lucide-react'
import { branchesApi } from '@/api/branches'
import {
  Button, Input, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, ConfirmDialog, BranchStatusBadge,
} from '@/components/ui'
import { formatDate } from '@/utils/format'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { BranchModal } from './BranchModal'
import type { Branch } from '@/types'

export function BranchesPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()

  const [page,   setPage]   = useState(1)
  const [q,      setQ]      = useState('')
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editing,   setEditing]   = useState<Branch | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['branches', { page, q: search }],
    queryFn:  () => branchesApi.list({ page, per_page: 20, q: search }),
  })

  const destroy = useMutation({
    mutationFn: (id: number) => branchesApi.destroy(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['branches'] })
      qc.invalidateQueries({ queryKey: ['branches-all'] })
      success('Branch deleted')
    },
    onError: apiError,
  })

  async function handleDelete(b: Branch) {
    const ok = await confirm({
      title:   `Delete ${b.name}?`,
      message: 'This will permanently delete the branch and cannot be undone.',
      danger:  true,
    })
    if (ok) destroy.mutate(b.id)
  }

  function handleEdit(b: Branch) {
    setEditing(b)
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
          <h1 className="text-xl font-bold text-gray-900">Branches</h1>
          <p className="text-sm text-gray-500">Physical cold-storage facilities</p>
        </div>
        <Button onClick={handleNew} size="sm">
          <Plus className="h-4 w-4" /> New Branch
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Branches</CardTitle>
          <form
            onSubmit={(e) => { e.preventDefault(); setSearch(q); setPage(1) }}
            className="flex items-center gap-2"
          >
            <Input
              placeholder="Search by name or code…"
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
              <TableTh>Branch</TableTh>
              <TableTh>Location</TableTh>
              <TableTh>Contact</TableTh>
              <TableTh>Chambers</TableTh>
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
            {data?.data.map((b) => (
              <TableRow key={b.id}>
                <TableTd>
                  <div className="flex items-center gap-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50">
                      <Building2 className="h-4 w-4 text-blue-500" />
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">{b.name}</p>
                      <p className="text-xs text-gray-400">{b.code}</p>
                    </div>
                  </div>
                </TableTd>
                <TableTd className="text-gray-500">
                  {[b.city, b.district].filter(Boolean).join(', ') || '—'}
                </TableTd>
                <TableTd className="text-gray-500">{b.phone ?? b.email ?? '—'}</TableTd>
                <TableTd className="tabular-nums">{b.chambers_count ?? 0}</TableTd>
                <TableTd>
                  <BranchStatusBadge status={b.status} />
                </TableTd>
                <TableTd className="text-gray-400">{formatDate(b.created_at)}</TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => handleEdit(b)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="text-red-400 hover:text-red-600"
                      disabled={(b.chambers_count ?? 0) > 0}
                      title={(b.chambers_count ?? 0) > 0 ? 'Cannot delete: branch still has chambers' : undefined}
                      onClick={() => handleDelete(b)}
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

      <BranchModal
        open={modalOpen}
        branch={editing}
        onClose={() => setModalOpen(false)}
      />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
