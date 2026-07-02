import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { BarChart3, Warehouse, Wallet, PackageSearch } from 'lucide-react'
import { reportsApi } from '@/api/reports'
import {
  Card, CardHeader, CardTitle, Input, Button, Badge,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
} from '@/components/ui'
import { formatMoney } from '@/utils/format'
import { cn } from '@/utils/cn'

function iso(d: Date): string {
  return d.toISOString().slice(0, 10)
}

export function ReportsPage() {
  const [from, setFrom] = useState(iso(new Date(Date.now() - 29 * 86_400_000)))
  const [to,   setTo]   = useState(iso(new Date()))
  const [range, setRange] = useState({ from, to })

  const occupancy = useQuery({
    queryKey: ['report-occupancy'],
    queryFn:  () => reportsApi.occupancy(),
  })
  const revenue = useQuery({
    queryKey: ['report-revenue', range],
    queryFn:  () => reportsApi.revenue(range),
  })
  const receivables = useQuery({
    queryKey: ['report-receivables'],
    queryFn:  () => reportsApi.receivables(),
  })
  const stock = useQuery({
    queryKey: ['report-stock'],
    queryFn:  () => reportsApi.stock(),
  })

  const maxDay = Math.max(
    1,
    ...(revenue.data?.series.map((s) => Math.max(s.billed_poisha, s.collected_poisha)) ?? [1]),
  )

  const agingLabels: Record<string, string> = {
    '0_30': '0–30 days', '31_60': '31–60 days', '61_90': '61–90 days', '90_plus': 'Over 90 days',
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-xl font-bold text-gray-900">Reports</h1>
        <p className="text-sm text-gray-500">Occupancy, revenue, receivables and stock</p>
      </div>

      {/* ── Revenue ─────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle><BarChart3 className="mr-1.5 inline h-4 w-4 text-blue-500" />Revenue — billed vs collected</CardTitle>
          <form
            className="flex items-center gap-2"
            onSubmit={(e) => { e.preventDefault(); setRange({ from, to }) }}
          >
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-36" />
            <span className="text-xs text-gray-400">to</span>
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-36" />
            <Button type="submit" size="sm" variant="outline">Apply</Button>
          </form>
        </CardHeader>
        <div className="px-4 pb-4">
          {revenue.isError && <p className="py-6 text-center text-sm text-red-500">Could not load revenue.</p>}
          {revenue.data && (
            <>
              <div className="mb-4 flex flex-wrap gap-6 text-sm">
                <div>
                  <p className="text-gray-400">Billed</p>
                  <p className="text-lg font-bold text-gray-900">{formatMoney(revenue.data.total_billed_poisha)}</p>
                </div>
                <div>
                  <p className="text-gray-400">Collected</p>
                  <p className="text-lg font-bold text-emerald-600">{formatMoney(revenue.data.total_collected_poisha)}</p>
                </div>
                <div>
                  <p className="text-gray-400">Collection rate</p>
                  <p className="text-lg font-bold text-gray-900">
                    {revenue.data.total_billed_poisha > 0
                      ? Math.round(revenue.data.total_collected_poisha / revenue.data.total_billed_poisha * 100)
                      : 0}%
                  </p>
                </div>
              </div>
              <div className="flex h-28 items-end gap-[2px]">
                {revenue.data.series.map((s) => (
                  <div key={s.date} className="group relative flex-1">
                    <div
                      className="w-full rounded-t bg-blue-200"
                      style={{ height: `${(s.billed_poisha / maxDay) * 96 + 2}px` }}
                    />
                    <div
                      className="w-full bg-emerald-400"
                      style={{ height: `${(s.collected_poisha / maxDay) * 96 + 1}px` }}
                    />
                    <div className="pointer-events-none absolute bottom-full left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-[10px] text-white group-hover:block">
                      {s.date}: billed {formatMoney(s.billed_poisha)}, collected {formatMoney(s.collected_poisha)}
                    </div>
                  </div>
                ))}
              </div>
              <p className="mt-1 text-center text-[10px] text-gray-400">
                {revenue.data.from} → {revenue.data.to} · blue = billed, green = collected
              </p>
            </>
          )}
        </div>
      </Card>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        {/* ── Occupancy ───────────────────────────────────────────────── */}
        <Card>
          <CardHeader>
            <CardTitle><Warehouse className="mr-1.5 inline h-4 w-4 text-blue-500" />Chamber occupancy</CardTitle>
          </CardHeader>
          <div className="space-y-3 px-4 pb-4">
            {occupancy.isError && <p className="py-6 text-center text-sm text-red-500">Could not load occupancy.</p>}
            {occupancy.data?.map((c) => (
              <div key={c.chamber_id}>
                <div className="mb-1 flex items-center justify-between text-sm">
                  <span className="font-medium text-gray-800">{c.name}</span>
                  <span className="text-gray-500">
                    {c.lots} lots · {Math.round(c.weight_kg).toLocaleString()} kg
                    {c.occupancy_pct !== null && ` · ${c.occupancy_pct}%`}
                  </span>
                </div>
                <div className="h-2.5 overflow-hidden rounded-full bg-gray-100">
                  <div
                    className={cn(
                      'h-full rounded-full',
                      (c.occupancy_pct ?? 0) > 90 ? 'bg-red-500'
                        : (c.occupancy_pct ?? 0) > 70 ? 'bg-amber-400' : 'bg-blue-500',
                    )}
                    style={{ width: `${Math.min(100, c.occupancy_pct ?? 0)}%` }}
                  />
                </div>
              </div>
            ))}
            {occupancy.data?.length === 0 && (
              <p className="py-6 text-center text-sm text-gray-400">No chambers yet.</p>
            )}
          </div>
        </Card>

        {/* ── Receivables ─────────────────────────────────────────────── */}
        <Card>
          <CardHeader>
            <CardTitle><Wallet className="mr-1.5 inline h-4 w-4 text-blue-500" />Receivables (dues)</CardTitle>
          </CardHeader>
          <div className="px-4 pb-4">
            {receivables.isError && <p className="py-6 text-center text-sm text-red-500">Could not load receivables.</p>}
            {receivables.data && (
              <>
                <p className="mb-3 text-sm text-gray-500">
                  Total outstanding:{' '}
                  <span className="text-lg font-bold text-red-600">
                    {formatMoney(receivables.data.total_due_poisha)}
                  </span>
                </p>
                <div className="mb-4 grid grid-cols-4 gap-2 text-center">
                  {Object.entries(receivables.data.aging).map(([bucket, amount]) => (
                    <div key={bucket} className="rounded-lg bg-gray-50 p-2">
                      <p className="text-[10px] uppercase tracking-wide text-gray-400">{agingLabels[bucket]}</p>
                      <p className="text-sm font-semibold text-gray-800">{formatMoney(amount)}</p>
                    </div>
                  ))}
                </div>
                <div className="space-y-1.5">
                  {receivables.data.customers.slice(0, 6).map((c) => (
                    <div key={c.customer_id} className="flex items-center justify-between text-sm">
                      <span className="text-gray-700">{c.name ?? c.code}</span>
                      <span className="flex items-center gap-2">
                        {c.oldest_days > 60 && <Badge variant="red">{c.oldest_days}d</Badge>}
                        <span className="font-medium text-gray-900">{formatMoney(c.due_poisha)}</span>
                      </span>
                    </div>
                  ))}
                  {receivables.data.customers.length === 0 && (
                    <p className="py-4 text-center text-sm text-gray-400">No outstanding dues 🎉</p>
                  )}
                </div>
              </>
            )}
          </div>
        </Card>
      </div>

      {/* ── Stock ───────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle><PackageSearch className="mr-1.5 inline h-4 w-4 text-blue-500" />Stock in storage</CardTitle>
          {stock.data && (
            <span className="text-sm text-gray-500">{stock.data.total_lots} active lots</span>
          )}
        </CardHeader>
        <div className="grid grid-cols-1 gap-0 lg:grid-cols-2">
          <Table>
            <TableHead>
              <tr>
                <TableTh>Product</TableTh>
                <TableTh>Lots</TableTh>
                <TableTh>Quantity</TableTh>
              </tr>
            </TableHead>
            <TableBody>
              {stock.isError && <TableError colSpan={3} error={stock.error} />}
              {stock.data?.by_product.length === 0 && <TableEmpty colSpan={3} />}
              {stock.data?.by_product.slice(0, 8).map((p) => (
                <TableRow key={p.product_id ?? 'none'}>
                  <TableTd className="font-medium text-gray-800">{p.name}</TableTd>
                  <TableTd className="text-gray-500">{p.lots}</TableTd>
                  <TableTd className="text-gray-500">
                    {p.quantity.toLocaleString()} {p.unit}
                  </TableTd>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <Table>
            <TableHead>
              <tr>
                <TableTh>Expiring lot</TableTh>
                <TableTh>Expiry</TableTh>
                <TableTh>Days left</TableTh>
              </tr>
            </TableHead>
            <TableBody>
              {stock.data?.expiring_soon.length === 0 && (
                <TableEmpty colSpan={3} message={`Nothing expires in the next ${stock.data?.expiring_days} days.`} />
              )}
              {stock.data?.expiring_soon.slice(0, 8).map((l) => (
                <TableRow key={l.lot_id}>
                  <TableTd>
                    <span className="font-medium text-gray-800">{l.lot_code}</span>
                    <span className="ml-1 text-xs text-gray-400">{l.product}</span>
                  </TableTd>
                  <TableTd className="text-gray-500">{l.expiry_date}</TableTd>
                  <TableTd>
                    <Badge variant={l.days_left <= 7 ? 'red' : l.days_left <= 14 ? 'yellow' : 'default'}>
                      {l.days_left}d
                    </Badge>
                  </TableTd>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </Card>
    </div>
  )
}
