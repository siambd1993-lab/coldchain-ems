import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { auditApi } from '@/api/reports'
import {
  Card, CardHeader, CardTitle, Input, Button, Badge,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination,
} from '@/components/ui'

function actionVariant(action: string): 'red' | 'green' | 'blue' | 'default' {
  if (action.includes('delete') || action.includes('void')) return 'red'
  if (action.includes('create') || action.includes('register')) return 'green'
  if (action.includes('update') || action.includes('change')) return 'blue'
  return 'default'
}

export function AuditPage() {
  const [page,   setPage]   = useState(1)
  const [q,      setQ]      = useState('')
  const [search, setSearch] = useState('')

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['audit-logs', { page, q: search }],
    queryFn:  () => auditApi.list({ page, per_page: 25, q: search }),
  })

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-xl font-bold text-gray-900">Audit Log</h1>
        <p className="text-sm text-gray-500">Who did what, and when — the immutable activity trail</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Activity</CardTitle>
          <form
            onSubmit={(e) => { e.preventDefault(); setSearch(q); setPage(1) }}
            className="flex items-center gap-2"
          >
            <Input
              placeholder="Search action, actor or text…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              prefix={<Search className="h-3.5 w-3.5" />}
              className="w-64"
            />
            <Button type="submit" size="sm" variant="outline">Search</Button>
          </form>
        </CardHeader>

        <Table>
          <TableHead>
            <tr>
              <TableTh>When</TableTh>
              <TableTh>Actor</TableTh>
              <TableTh>Action</TableTh>
              <TableTh>Details</TableTh>
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={4} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && <TableError colSpan={4} error={error} />}
            {!isLoading && !isError && data?.data.length === 0 && <TableEmpty colSpan={4} />}
            {data?.data.map((log) => (
              <TableRow key={log.id}>
                <TableTd className="whitespace-nowrap text-gray-400">
                  {log.created_at ? new Date(log.created_at).toLocaleString() : '—'}
                </TableTd>
                <TableTd>
                  <span className="text-gray-700">{log.actor_label ?? log.actor_type}</span>
                </TableTd>
                <TableTd>
                  <Badge variant={actionVariant(log.action)}>{log.action}</Badge>
                </TableTd>
                <TableTd className="max-w-md">
                  <p className="truncate text-gray-600" title={log.description ?? undefined}>
                    {log.description ?? '—'}
                  </p>
                  {log.subject && <p className="text-xs text-gray-400">{log.subject}</p>}
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
