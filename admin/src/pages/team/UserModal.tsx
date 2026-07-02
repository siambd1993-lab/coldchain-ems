import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { usersApi, rolesApi } from '@/api/team'
import { Modal, ModalFooter, Button, Input, Select } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import type { User } from '@/types'

const schema = z.object({
  name:     z.string().min(1, 'Required').max(255),
  email:    z.string().email('Enter a valid email'),
  phone:    z.string().optional(),
  password: z.string().optional(),
  status:   z.enum(['active', 'suspended']),
  role_ids: z.array(z.number()).optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  open:    boolean
  onClose: () => void
  user?:   User | null
}

export function UserModal({ open, onClose, user }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { data: roles } = useQuery({
    queryKey: ['roles-all'],
    queryFn:  () => rolesApi.list(),
    enabled:  open,
    staleTime: 60_000,
  })

  const {
    register,
    handleSubmit,
    reset,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { status: 'active', role_ids: [] },
  })

  const selectedRoles = watch('role_ids') ?? []

  useEffect(() => {
    if (open) {
      if (user) {
        reset({
          name:     user.name,
          email:    user.email,
          phone:    user.phone ?? '',
          password: '',
          status:   user.status === 'suspended' ? 'suspended' : 'active',
          role_ids: user.role_ids ?? [],
        })
      } else {
        reset({ status: 'active', role_ids: [] })
      }
    }
  }, [open, user, reset])

  function toggleRole(id: number) {
    const next = selectedRoles.includes(id)
      ? selectedRoles.filter((r) => r !== id)
      : [...selectedRoles, id]
    setValue('role_ids', next, { shouldDirty: true })
  }

  const save = useMutation({
    mutationFn: async (values: FormValues) => {
      if (user) {
        await usersApi.update(user.id, {
          name:     values.name,
          email:    values.email,
          phone:    values.phone || undefined,
          password: values.password || undefined,
          status:   values.status,
        })
        // Role changes go through the dedicated endpoint.
        await usersApi.syncRoles(user.id, values.role_ids ?? [])
      } else {
        if (!values.password || values.password.length < 8) {
          throw Object.assign(new Error('validation'), {
            response: { data: { error: { message: 'Password of at least 8 characters is required for a new user.', code: 'VALIDATION_ERROR', details: null, request_id: '' } } },
          })
        }
        await usersApi.create({
          name:     values.name,
          email:    values.email,
          phone:    values.phone || undefined,
          password: values.password,
          status:   values.status,
          role_ids: values.role_ids,
        })
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['users'] })
      qc.invalidateQueries({ queryKey: ['roles-all'] })
      success(user ? 'User updated' : 'User created')
      onClose()
    },
    onError: apiError,
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={user ? `Edit ${user.name}` : 'New Staff Account'}
      size="lg"
    >
      <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input label="Full name *" error={errors.name?.message} {...register('name')} />
          <Input label="Email *" type="email" error={errors.email?.message} {...register('email')} />
          <Input label="Phone" error={errors.phone?.message} {...register('phone')} />
          <Input
            label={user ? 'New password (leave blank to keep)' : 'Password *'}
            type="password"
            autoComplete="new-password"
            hint="Minimum 8 characters"
            error={errors.password?.message}
            {...register('password')}
          />
          <Select
            label="Status"
            options={[
              { value: 'active',    label: 'Active'    },
              { value: 'suspended', label: 'Suspended' },
            ]}
            error={errors.status?.message}
            {...register('status')}
          />
        </div>

        <div className="mt-4">
          <p className="mb-2 text-sm font-medium text-gray-700">Roles</p>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {roles?.data.map((role) => (
              <label
                key={role.id}
                className="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-2.5 text-sm hover:bg-gray-50"
              >
                <input
                  type="checkbox"
                  className="mt-0.5"
                  checked={selectedRoles.includes(role.id)}
                  onChange={() => toggleRole(role.id)}
                />
                <span>
                  <span className="font-medium text-gray-800">{role.name}</span>
                  {role.description && (
                    <span className="block text-xs text-gray-400">{role.description}</span>
                  )}
                </span>
              </label>
            ))}
          </div>
        </div>

        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" onClick={onClose} type="button">Cancel</Button>
          <Button size="sm" type="submit" loading={isSubmitting || save.isPending}>
            {user ? 'Update' : 'Create'}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
