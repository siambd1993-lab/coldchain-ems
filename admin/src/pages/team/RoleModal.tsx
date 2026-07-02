import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { rolesApi } from '@/api/team'
import { Modal, ModalFooter, Button, Input, Textarea } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import type { Role } from '@/types'

interface Props {
  open:    boolean
  onClose: () => void
  role?:   Role | null
}

export function RoleModal({ open, onClose, role }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const [name, setName]               = useState('')
  const [description, setDescription] = useState('')
  const [selected, setSelected]       = useState<Set<string>>(new Set())

  const { data: groups } = useQuery({
    queryKey: ['permission-catalog'],
    queryFn:  () => rolesApi.permissions(),
    enabled:  open,
    staleTime: 5 * 60_000,
  })

  useEffect(() => {
    if (open) {
      setName(role?.name ?? '')
      setDescription(role?.description ?? '')
      setSelected(new Set(role?.permissions ?? []))
    }
  }, [open, role])

  function toggle(value: string) {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(value)) next.delete(value)
      else next.add(value)
      return next
    })
  }

  function toggleModule(values: string[]) {
    setSelected((prev) => {
      const next = new Set(prev)
      const allOn = values.every((v) => next.has(v))
      values.forEach((v) => (allOn ? next.delete(v) : next.add(v)))
      return next
    })
  }

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        name,
        description: description || undefined,
        permissions: Array.from(selected),
      }
      return role ? rolesApi.update(role.id, payload) : rolesApi.create(payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['roles'] })
      qc.invalidateQueries({ queryKey: ['roles-all'] })
      success(role ? 'Role updated' : 'Role created')
      onClose()
    },
    onError: apiError,
  })

  const readOnly = role?.is_system === true

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={role ? (readOnly ? `${role.name} (system role)` : `Edit ${role.name}`) : 'New Custom Role'}
      size="lg"
    >
      <div className="flex flex-col gap-4">
        {readOnly && (
          <p className="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-700">
            System roles are read-only. Create a custom role to define your own permission set.
          </p>
        )}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            label="Role name *"
            value={name}
            onChange={(e) => setName(e.target.value)}
            readOnly={readOnly}
          />
          <div className="sm:col-span-1">
            <Textarea
              label="Description"
              rows={1}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              readOnly={readOnly}
            />
          </div>
        </div>

        <div>
          <p className="mb-2 text-sm font-medium text-gray-700">
            Permissions <span className="text-xs font-normal text-gray-400">({selected.size} selected)</span>
          </p>
          <div className="max-h-80 space-y-3 overflow-y-auto rounded-lg border border-gray-100 p-3">
            {groups?.map((group) => {
              const values = group.permissions.map((p) => p.value)
              const onCount = values.filter((v) => selected.has(v)).length
              return (
                <div key={group.module}>
                  <button
                    type="button"
                    disabled={readOnly}
                    onClick={() => toggleModule(values)}
                    className="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500 hover:text-blue-600 disabled:cursor-default"
                  >
                    {group.label} {onCount > 0 && <span className="text-blue-500">({onCount})</span>}
                  </button>
                  <div className="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                    {group.permissions.map((p) => (
                      <label key={p.value} className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                        <input
                          type="checkbox"
                          disabled={readOnly}
                          checked={selected.has(p.value)}
                          onChange={() => toggle(p.value)}
                        />
                        {p.label}
                      </label>
                    ))}
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      </div>

      <ModalFooter className="-mx-5 mt-4">
        <Button variant="outline" size="sm" onClick={onClose} type="button">
          {readOnly ? 'Close' : 'Cancel'}
        </Button>
        {!readOnly && (
          <Button
            size="sm"
            onClick={() => save.mutate()}
            disabled={!name || selected.size === 0}
            loading={save.isPending}
          >
            {role ? 'Update' : 'Create'}
          </Button>
        )}
      </ModalFooter>
    </Modal>
  )
}
