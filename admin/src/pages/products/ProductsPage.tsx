import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil, Trash2 } from 'lucide-react'
import { productsApi } from '@/api/products'
import {
  Button, Input, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, ConfirmDialog, Badge,
} from '@/components/ui'
import { formatDate } from '@/utils/format'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { ProductModal } from './ProductModal'
import type { Product } from '@/types'

export function ProductsPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()

  const [page,   setPage]   = useState(1)
  const [q,      setQ]      = useState('')
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editing,   setEditing]   = useState<Product | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['products', { page, q: search }],
    queryFn:  () => productsApi.list({ page, per_page: 20, q: search }),
  })

  const destroy = useMutation({
    mutationFn: (id: number) => productsApi.destroy(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['products'] })
      success('Product deleted')
    },
    onError: apiError,
  })

  async function handleDelete(p: Product) {
    const ok = await confirm({
      title:   `Delete ${p.name}?`,
      message: 'Existing stock lots keep their history; the product will no longer be selectable for new intakes.',
      danger:  true,
    })
    if (ok) destroy.mutate(p.id)
  }

  function handleEdit(p: Product) {
    setEditing(p)
    setModalOpen(true)
  }

  function handleNew() {
    setEditing(null)
    setModalOpen(true)
  }

  function tempRange(p: Product): string {
    if (p.default_temp_min_c == null && p.default_temp_max_c == null) return '—'
    const min = p.default_temp_min_c != null ? `${p.default_temp_min_c}°` : '…'
    const max = p.default_temp_max_c != null ? `${p.default_temp_max_c}°` : '…'
    return `${min} to ${max}`
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Products</h1>
          <p className="text-sm text-gray-500">Commodity catalogue for stock intakes</p>
        </div>
        <Button onClick={handleNew} size="sm">
          <Plus className="h-4 w-4" /> New Product
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Products</CardTitle>
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
              <TableTh>Product</TableTh>
              <TableTh>Category</TableTh>
              <TableTh>Unit</TableTh>
              <TableTh>Storage Temp</TableTh>
              <TableTh>Shelf Life</TableTh>
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
            {data?.data.map((p) => (
              <TableRow key={p.id}>
                <TableTd>
                  <div>
                    <p className="font-medium text-gray-900">{p.name}</p>
                    <p className="text-xs text-gray-400">{p.code}</p>
                  </div>
                </TableTd>
                <TableTd>
                  {p.category ? <Badge variant="blue">{p.category}</Badge> : '—'}
                </TableTd>
                <TableTd className="text-gray-500">{p.unit_of_measure}</TableTd>
                <TableTd className="tabular-nums text-gray-500">{tempRange(p)}</TableTd>
                <TableTd className="text-gray-500">
                  {p.shelf_life_days != null ? `${p.shelf_life_days} days` : '—'}
                </TableTd>
                <TableTd className="text-gray-400">{formatDate(p.created_at)}</TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => handleEdit(p)}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="text-red-400 hover:text-red-600"
                      onClick={() => handleDelete(p)}
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

      <ProductModal
        open={modalOpen}
        product={editing}
        onClose={() => setModalOpen(false)}
      />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
