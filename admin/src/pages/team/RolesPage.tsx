import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, Trash2, Eye, ShieldCheck } from 'lucide-react'
import { rolesApi } from '@/api/team'
import {
  Button, Card, CardHeader, CardTitle,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  ConfirmDialog, Badge,
} from '@/components/ui'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import { RoleModal } from './RoleModal'
import type { Role } from '@/types'

export function RolesPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()

  const [modalOpen, setModalOpen] = useState(false)
  const [editing,   setEditing]   = useState<Role | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['roles'],
    queryFn:  () => rolesApi.list(),
  })

  const destroy = useMutation({
    mutationFn: (id: number) => rolesApi.destroy(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['roles'] })
      success('Role deleted')
    },
    onError: apiError,
  })

  async function handleDelete(role: Role) {
    const ok = await confirm({
      title:   `Delete role "${role.name}"?`,
      message: 'Only possible when no user holds this role.',
      danger:  true,
    })
    if (ok) destroy.mutate(role.id)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Roles & Permissions</h1>
          <p className="text-sm text-gray-500">
            System roles are fixed; custom roles carry exactly the permissions you pick
          </p>
        </div>
        <Button size="sm" onClick={() => { setEditing(null); setModalOpen(true) }}>
          <Plus className="h-4 w-4" /> New Custom Role
        </Button>
      </div>

      <Card>
        <CardHeader><CardTitle>All Roles</CardTitle></CardHeader>
        <Table>
          <TableHead>
            <tr>
              <TableTh>Role</TableTh>
              <TableTh>Type</TableTh>
              <TableTh>Permissions</TableTh>
              <TableTh>Users</TableTh>
              <TableTh />
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={5} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && <TableError colSpan={5} error={error} />}
            {!isLoading && !isError && data?.data.length === 0 && <TableEmpty colSpan={5} />}
            {data?.data.map((role) => (
              <TableRow key={role.id}>
                <TableTd>
                  <div>
                    <p className="font-medium text-gray-900">{role.name}</p>
                    {role.description && <p className="text-xs text-gray-400">{role.description}</p>}
                  </div>
                </TableTd>
                <TableTd>
                  {role.is_system
                    ? <Badge variant="blue"><ShieldCheck className="mr-1 h-3 w-3" />system</Badge>
                    : <Badge variant="default">custom</Badge>}
                </TableTd>
                <TableTd className="text-gray-500">{role.permissions.length}</TableTd>
                <TableTd className="text-gray-500">{role.users_count ?? 0}</TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => { setEditing(role); setModalOpen(true) }}>
                      {role.is_system ? <Eye className="h-3.5 w-3.5" /> : <Pencil className="h-3.5 w-3.5" />}
                    </Button>
                    {!role.is_system && (
                      <Button
                        variant="ghost"
                        size="icon"
                        className="text-red-400 hover:text-red-600"
                        onClick={() => handleDelete(role)}
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
      </Card>

      <RoleModal open={modalOpen} role={editing} onClose={() => setModalOpen(false)} />
      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
