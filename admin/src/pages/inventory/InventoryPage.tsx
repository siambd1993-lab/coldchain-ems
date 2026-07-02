import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, ArrowUpFromLine, SlidersHorizontal } from 'lucide-react'
import { inventoryApi } from '@/api/inventory'
import {
  Button, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, LotStatusBadge,
} from '@/components/ui'
import { formatDate, formatQuantity } from '@/utils/format'
import { useToast } from '@/hooks/useToast'
import { IntakeModal }   from './IntakeModal'
import { ReleaseModal }  from './ReleaseModal'
import { AdjustModal }   from './AdjustModal'
import type { StockLot } from '@/types'

export function InventoryPage() {
  const [page, setPage] = useState(1)
  const [intakeOpen,  setIntakeOpen]  = useState(false)
  const [releaseOpen, setReleaseOpen] = useState(false)
  const [adjustOpen,  setAdjustOpen]  = useState(false)
  const [selectedLot, setSelectedLot] = useState<StockLot | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['lots', { page }],
    queryFn:  () => inventoryApi.listLots({ page, per_page: 20 }),
  })

  function openRelease(lot: StockLot) {
    setSelectedLot(lot)
    setReleaseOpen(true)
  }

  function openAdjust(lot: StockLot) {
    setSelectedLot(lot)
    setAdjustOpen(true)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Inventory</h1>
          <p className="text-sm text-gray-500">Stock lots and movements</p>
        </div>
        <Button onClick={() => setIntakeOpen(true)} size="sm">
          <Plus className="h-4 w-4" /> New Intake
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Stock Lots</CardTitle>
        </CardHeader>
        <Table>
          <TableHead>
            <tr>
              <TableTh>Lot Code</TableTh>
              <TableTh>Product</TableTh>
              <TableTh>Customer</TableTh>
              <TableTh>Received</TableTh>
              <TableTh>Initial (kg)</TableTh>
              <TableTh>Current (kg)</TableTh>
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
            {data?.data.map((lot) => (
              <TableRow key={lot.id}>
                <TableTd className="font-mono text-xs">{lot.lot_code}</TableTd>
                <TableTd>{lot.product?.name ?? '—'}</TableTd>
                <TableTd>{lot.customer?.name ?? '—'}</TableTd>
                <TableTd className="text-gray-400">{formatDate(lot.received_at)}</TableTd>
                <TableTd className="tabular-nums">{formatQuantity(lot.initial_quantity)}</TableTd>
                <TableTd className="tabular-nums font-medium">{formatQuantity(lot.quantity)}</TableTd>
                <TableTd>
                  <LotStatusBadge status={lot.status} />
                </TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    {lot.status !== 'released' && (
                      <>
                        <Button
                          variant="ghost"
                          size="xs"
                          onClick={() => openRelease(lot)}
                          title="Release"
                        >
                          <ArrowUpFromLine className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="xs"
                          onClick={() => openAdjust(lot)}
                          title="Adjust"
                        >
                          <SlidersHorizontal className="h-3.5 w-3.5" />
                        </Button>
                      </>
                    )}
                  </div>
                </TableTd>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        {data?.meta && (
          <Pagination
            meta={data.meta}
            onPage={setPage}
            className="border-t border-gray-100 px-4"
          />
        )}
      </Card>

      <IntakeModal  open={intakeOpen}  onClose={() => setIntakeOpen(false)} />
      <ReleaseModal open={releaseOpen} onClose={() => setReleaseOpen(false)} lot={selectedLot} />
      <AdjustModal  open={adjustOpen}  onClose={() => setAdjustOpen(false)}  lot={selectedLot} />
    </div>
  )
}
