import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { BellRing, Check, CheckCheck } from 'lucide-react'
import { alertsApi } from '@/api/iot'
import {
  Button, Select, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, Badge,
} from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import type { AlertRow } from '@/types'

const SEVERITY_VARIANT: Record<AlertRow['severity'], 'default' | 'yellow' | 'red' | 'purple'> = {
  info: 'default', warning: 'yellow', critical: 'red', emergency: 'purple',
}
const STATUS_VARIANT: Record<AlertRow['status'], 'red' | 'yellow' | 'green' | 'default'> = {
  active: 'red', acknowledged: 'yellow', resolved: 'green', suppressed: 'default',
}

export function AlertsPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const [page,     setPage]     = useState(1)
  const [status,   setStatus]   = useState('')
  const [severity, setSeverity] = useState('')

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['alerts', { page, status, severity }],
    queryFn:  () => alertsApi.list({
      page, per_page: 20,
      status: status || undefined,
      severity: severity || undefined,
    }),
  })

  const ack = useMutation({
    mutationFn: (id: number) => alertsApi.acknowledge(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['alerts'] }); success('Alert acknowledged') },
    onError: apiError,
  })
  const resolve = useMutation({
    mutationFn: (id: number) => alertsApi.resolve(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['alerts'] }); success('Alert resolved') },
    onError: apiError,
  })

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-xl font-bold text-gray-900">Alerts</h1>
        <p className="text-sm text-gray-500">Temperature excursions, power events and device faults</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle><BellRing className="mr-1.5 inline h-4 w-4 text-blue-500" />Alert inbox</CardTitle>
          <div className="flex items-center gap-2">
            <Select
              options={[
                { value: '', label: 'All statuses' },
                { value: 'active', label: 'Active' },
                { value: 'acknowledged', label: 'Acknowledged' },
                { value: 'resolved', label: 'Resolved' },
              ]}
              value={status}
              onChange={(e) => { setStatus(e.target.value); setPage(1) }}
              className="w-40"
            />
            <Select
              options={[
                { value: '', label: 'All severities' },
                { value: 'critical', label: 'Critical' },
                { value: 'warning', label: 'Warning' },
                { value: 'info', label: 'Info' },
              ]}
              value={severity}
              onChange={(e) => { setSeverity(e.target.value); setPage(1) }}
              className="w-40"
            />
          </div>
        </CardHeader>

        <Table>
          <TableHead>
            <tr>
              <TableTh>Alert</TableTh>
              <TableTh>Severity</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Chamber</TableTh>
              <TableTh>Triggered</TableTh>
              <TableTh />
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={6} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && <TableError colSpan={6} error={error} />}
            {!isLoading && !isError && data?.data.length === 0 && (
              <TableEmpty colSpan={6} message="No alerts — the cold chain is happy. ✅" />
            )}
            {data?.data.map((a) => (
              <TableRow key={a.id}>
                <TableTd className="max-w-sm">
                  <p className="font-medium text-gray-900">{a.title}</p>
                  {a.message && <p className="truncate text-xs text-gray-400" title={a.message}>{a.message}</p>}
                  {a.resolution_note && (
                    <p className="text-xs text-emerald-600">↳ {a.resolution_note}</p>
                  )}
                </TableTd>
                <TableTd><Badge variant={SEVERITY_VARIANT[a.severity]}>{a.severity}</Badge></TableTd>
                <TableTd><Badge variant={STATUS_VARIANT[a.status]}>{a.status}</Badge></TableTd>
                <TableTd className="text-gray-500">{a.chamber?.name ?? '—'}</TableTd>
                <TableTd className="whitespace-nowrap text-gray-400">
                  {a.triggered_at ? new Date(a.triggered_at).toLocaleString() : '—'}
                </TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    {a.status === 'active' && (
                      <Button size="sm" variant="outline" onClick={() => ack.mutate(a.id)} loading={ack.isPending}>
                        <Check className="h-3.5 w-3.5" /> Ack
                      </Button>
                    )}
                    {(a.status === 'active' || a.status === 'acknowledged') && (
                      <Button size="sm" variant="outline" onClick={() => resolve.mutate(a.id)} loading={resolve.isPending}>
                        <CheckCheck className="h-3.5 w-3.5" /> Resolve
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
    </div>
  )
}
