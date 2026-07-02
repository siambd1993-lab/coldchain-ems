import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Warehouse } from 'lucide-react'
import { chambersApi } from '@/api/chambers'
import {
  Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, Badge, ChamberStatusBadge,
} from '@/components/ui'
import { formatDate } from '@/utils/format'

export function ChambersPage() {
  const [page, setPage] = useState(1)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['chambers', { page }],
    queryFn:  () => chambersApi.list({ page, per_page: 20 }),
  })

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-xl font-bold text-gray-900">Chambers</h1>
        <p className="text-sm text-gray-500">Cold storage chambers and storage units</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Chambers</CardTitle>
        </CardHeader>
        <Table>
          <TableHead>
            <tr>
              <TableTh>Name</TableTh>
              <TableTh>Type</TableTh>
              <TableTh>Temp Band (°C)</TableTh>
              <TableTh>Capacity (kg)</TableTh>
              <TableTh>Current Temp</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Created</TableTh>
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
                  <div className="flex items-center gap-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50">
                      <Warehouse className="h-4 w-4 text-blue-500" />
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">{c.name}</p>
                      <p className="text-xs text-gray-400">{c.floor ?? c.zone ?? c.code}</p>
                    </div>
                  </div>
                </TableTd>
                <TableTd>
                  <Badge variant="blue">{c.chamber_type?.replace(/_/g, ' ')}</Badge>
                </TableTd>
                <TableTd className="tabular-nums text-gray-600">
                  {c.target_band?.temp_min_c ?? '—'} to {c.target_band?.temp_max_c ?? '—'}
                </TableTd>
                <TableTd className="tabular-nums">
                  {c.capacity?.weight_kg != null
                    ? new Intl.NumberFormat('en-BD').format(c.capacity.weight_kg)
                    : '—'}
                </TableTd>
                <TableTd className="tabular-nums text-gray-600">
                  {c.current?.temp_c != null ? `${c.current.temp_c}°C` : '—'}
                </TableTd>
                <TableTd>
                  <ChamberStatusBadge status={c.status} />
                </TableTd>
                <TableTd className="text-gray-400">{formatDate(c.created_at)}</TableTd>
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
    </div>
  )
}
