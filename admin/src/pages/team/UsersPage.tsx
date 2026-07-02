import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil, Trash2 } from 'lucide-react'
import { usersApi } from '@/api/team'
import {
  Button, Input, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, ConfirmDialog, Badge,
} from '@/components/ui'
import { formatDate } from '@/utils/format'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { UserModal } from './UserModal'
import type { User } from '@/types'

const STATUS_VARIANT: Record<string, 'green' | 'red' | 'default'> = {
  active: 'green', suspended: 'red',
}

export function UsersPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()
  const me = useAuthStore((s) => s.user)

  const [page,   setPage]   = useState(1)
  const [q,      setQ]      = useState('')
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editing,   setEditing]   = useState<User | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['users', { page, q: search }],
    queryFn:  () => usersApi.list({ page, per_page: 20, q: search }),
  })

  const destroy = useMutation({
    mutationFn: (id: number) => usersApi.destroy(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['users'] })
      success('User deleted')
    },
    onError: apiError,
  })

  async function handleDelete(u: User) {
    const ok = await confirm({
      title:   `Delete ${u.name}?`,
      message: 'The account is removed and all its sessions are revoked.',
      danger:  true,
    })
    if (ok) destroy.mutate(u.id)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Team</h1>
          <p className="text-sm text-gray-500">Staff accounts and their roles</p>
        </div>
        <Button size="sm" onClick={() => { setEditing(null); setModalOpen(true) }}>
          <Plus className="h-4 w-4" /> New User
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Users</CardTitle>
          <form
            onSubmit={(e) => { e.preventDefault(); setSearch(q); setPage(1) }}
            className="flex items-center gap-2"
          >
            <Input
              placeholder="Search name or email…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              prefix={<Search className="h-3.5 w-3.5" />}
              className="w-56"
            />
            <Button type="submit" size="sm" variant="outline">Search</Button>
          </form>
        </CardHeader>

        <Table>
          <TableHead>
            <tr>
              <TableTh>User</TableTh>
              <TableTh>Roles</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Last Login</TableTh>
              <TableTh>Created</TableTh>
              <TableTh />
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={6} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && <TableError colSpan={6} error={error} />}
            {!isLoading && !isError && data?.data.length === 0 && <TableEmpty colSpan={6} />}
            {data?.data.map((u) => (
              <TableRow key={u.id}>
                <TableTd>
                  <div>
                    <p className="font-medium text-gray-900">
                      {u.name}
                      {u.id === me?.id && <span className="ml-1.5 text-xs text-blue-500">(you)</span>}
                    </p>
                    <p className="text-xs text-gray-400">{u.email}</p>
                  </div>
                </TableTd>
                <TableTd>
                  <div className="flex flex-wrap gap-1">
                    {u.roles.length === 0 && <span className="text-xs text-gray-400">no roles</span>}
                    {u.roles.map((r) => (
                      <Badge key={r} variant="blue">{r.replace(/_/g, ' ')}</Badge>
                    ))}
                  </div>
                </TableTd>
                <TableTd>
                  <Badge variant={STATUS_VARIANT[u.status] ?? 'default'}>{u.status}</Badge>
                </TableTd>
                <TableTd className="text-gray-400">
                  {u.last_login_at ? formatDate(u.last_login_at) : 'never'}
                </TableTd>
                <TableTd className="text-gray-400">{formatDate(u.created_at)}</TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => { setEditing(u); setModalOpen(true) }}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    {u.id !== me?.id && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="text-red-400 hover:text-red-600"
                        onClick={() => handleDelete(u)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
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

      <UserModal open={modalOpen} user={editing} onClose={() => setModalOpen(false)} />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
