import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil, Trash2, Box } from 'lucide-react'
import { chambersApi, storageUnitsApi } from '@/api/chambers'
import {
  Button, Input, Select, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty,
  Pagination, ConfirmDialog, Badge, UnitStatusBadge,
} from '@/components/ui'
import { formatDate, formatQuantity } from '@/utils/format'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { StorageUnitModal } from './StorageUnitModal'
import type { StorageUnit } from '@/types'

export function StorageUnitsPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()

  const [page,      setPage]      = useState(1)
  const [q,         setQ]         = useState('')
  const [search,    setSearch]    = useState('')
  const [chamberId, setChamberId] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editing,   setEditing]   = useState<StorageUnit | null>(null)

  const { data: chambers } = useQuery({
    queryKey: ['chambers-all'],
    queryFn:  () => chambersApi.list({ per_page: 100 }),
  })

  const { data, isLoading } = useQuery({
    queryKey: ['storage-units', { page, q: search, chamberId }],
    queryFn:  () => storageUnitsApi.list({
      page,
      per_page:        20,
      q:               search || undefined,
      chamber_id:      chamberId ? Number(chamberId) : undefined,
      include_chamber: true,
    }),
  })

  const destroy = useMutation({
    mutationFn: (id: number) => storageUnitsApi.destroy(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['storage-units'] })
      success('Storage unit deleted')
    },
    onError: apiError,
  })

  async function handleDelete(u: StorageUnit) {
    const ok = await confirm({
      title:   `Delete ${u.code}?`,
      message: 'This will permanently delete the storage unit and cannot be undone.',
      danger:  true,
    })
    if (ok) destroy.mutate(u.id)
  }

  function handleEdit(u: StorageUnit) {
    setEditing(u)
    setModalOpen(true)
  }

  function handleNew() {
    setEditing(null)
    setModalOpen(true)
  }

  const chamberOpts = [
    { value: '', label: 'All chambers' },
    ...(chambers?.data.map((c) => ({ value: String(c.id), label: `${c.name} (${c.code})` })) ?? []),
  ]

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Storage Units</h1>
          <p className="text-sm text-gray-500">Rentable subdivisions within chambers</p>
        </div>
        <Button onClick={handleNew} size="sm">
          <Plus className="h-4 w-4" /> New Storage Unit
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Storage Units</CardTitle>
          <div className="flex items-center gap-2">
            <Select
              options={chamberOpts}
              value={chamberId}
              onChange={(e) => { setChamberId(e.target.value); setPage(1) }}
              className="w-48"
            />
            <form
              onSubmit={(e) => { e.preventDefault(); setSearch(q); setPage(1) }}
              className="flex items-center gap-2"
            >
              <Input
                placeholder="Search by code or label…"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                prefix={<Search className="h-3.5 w-3.5" />}
                className="w-52"
              />
              <Button type="submit" size="sm" variant="outline">Search</Button>
            </form>
          </div>
        </CardHeader>

        <Table>
          <TableHead>
            <tr>
              <TableTh>Unit</TableTh>
              <TableTh>Chamber</TableTh>
              <TableTh>Type</TableTh>
              <TableTh>Capacity</TableTh>
              <TableTh>Occupied / Available</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Created</TableTh>
              <TableTh />
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={8} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {!isLoading && data?.data.length === 0 && <TableEmpty colSpan={8} />}
            {data?.data.map((u) => (
              <TableRow key={u.id}>
                <TableTd>
                  <div className="flex items-center gap-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50">
                      <Box className="h-4 w-4 text-blue-500" />
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">{u.code}</p>
                      <p className="text-xs text-gray-400">{u.label ?? '—'}</p>
                    </div>
                  </div>
                </TableTd>
                <TableTd className="text-gray-500">
                  {u.chamber ? `${u.chamber.name} (${u.chamber.code})` : '—'}
                </TableTd>
                <TableTd>
                  <Badge variant="blue">{u.unit_type.replace(/_/g, ' ')}</Badge>
                </TableTd>
                <TableTd className="tabular-nums text-gray-600">
                  {formatQuantity(u.capacity_weight_kg, 'kg')}
                </TableTd>
                <TableTd className="tabular-nums text-gray-600">
                  {formatQuantity(u.occupied_weight_kg, 'kg')} / {formatQuantity(u.available_weight_kg, 'kg')}
                </TableTd>
                <TableTd>
                  <UnitStatusBadge status={u.status} />
                </TableTd>
                <TableTd className="text-gray-400">{formatDate(u.created_at)}</TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => handleEdit(u)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="text-red-400 hover:text-red-600"
                      onClick={() => handleDelete(u)}
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

      <StorageUnitModal
        open={modalOpen}
        unit={editing}
        defaultChamberId={chamberId ? Number(chamberId) : undefined}
        onClose={() => setModalOpen(false)}
      />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
