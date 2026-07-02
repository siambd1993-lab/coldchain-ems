import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { branchesApi } from '@/api/branches'
import { Modal, ModalFooter, Button, Input, Select } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import type { Branch } from '@/types'

const schema = z.object({
  code:          z.string().min(1, 'Required').max(64),
  name:          z.string().min(1, 'Required').max(255),
  status:        z.enum(['active', 'inactive', 'under_maintenance']),
  address_line1: z.string().optional(),
  address_line2: z.string().optional(),
  city:          z.string().optional(),
  district:      z.string().optional(),
  division:      z.string().optional(),
  postal_code:   z.string().optional(),
  country:       z.string().max(2, 'Use ISO 2-letter code').optional(),
  latitude:      z.string().optional(),
  longitude:     z.string().optional(),
  phone:         z.string().optional(),
  email:         z.string().email().optional().or(z.literal('')),
  timezone:      z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  open:    boolean
  onClose: () => void
  branch?: Branch | null
}

export function BranchModal({ open, onClose, branch }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { status: 'active', country: 'BD' },
  })

  useEffect(() => {
    if (open) {
      if (branch) {
        reset({
          code:          branch.code,
          name:          branch.name,
          status:        branch.status,
          address_line1: branch.address_line1 ?? '',
          address_line2: branch.address_line2 ?? '',
          city:          branch.city ?? '',
          district:      branch.district ?? '',
          division:      branch.division ?? '',
          postal_code:   branch.postal_code ?? '',
          country:       branch.country ?? '',
          latitude:      branch.latitude  != null ? String(branch.latitude)  : '',
          longitude:     branch.longitude != null ? String(branch.longitude) : '',
          phone:         branch.phone ?? '',
          email:         branch.email ?? '',
          timezone:      branch.timezone ?? '',
        })
      } else {
        // Branch codes are meaningful short identifiers (e.g. "DHK-02") —
        // left blank deliberately rather than auto-generated like customer codes.
        reset({ status: 'active', country: 'BD' })
      }
    }
  }, [open, branch, reset])

  const save = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = {
        code:          values.code,
        name:          values.name,
        status:        values.status,
        address_line1: values.address_line1 || undefined,
        address_line2: values.address_line2 || undefined,
        city:          values.city || undefined,
        district:      values.district || undefined,
        division:      values.division || undefined,
        postal_code:   values.postal_code || undefined,
        country:       values.country || undefined,
        latitude:      values.latitude  ? Number(values.latitude)  : undefined,
        longitude:     values.longitude ? Number(values.longitude) : undefined,
        phone:         values.phone || undefined,
        email:         values.email || undefined,
        timezone:      values.timezone || undefined,
      }
      return branch
        ? branchesApi.update(branch.id, payload)
        : branchesApi.create(payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['branches'] })
      qc.invalidateQueries({ queryKey: ['branches-all'] })
      success(branch ? 'Branch updated' : 'Branch created')
      onClose()
    },
    onError: apiError,
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={branch ? 'Edit Branch' : 'New Branch'}
      size="xl"
    >
      <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            label="Branch code *"
            placeholder="DHK-02"
            hint={branch ? 'Code cannot be changed after creation' : 'Short, unique identifier for this branch'}
            error={errors.code?.message}
            readOnly={!!branch}
            {...register('code')}
          />
          <Input
            label="Name *"
            error={errors.name?.message}
            {...register('name')}
          />
          <Select
            label="Status"
            options={[
              { value: 'active',            label: 'Active'            },
              { value: 'inactive',          label: 'Inactive'          },
              { value: 'under_maintenance', label: 'Under Maintenance' },
            ]}
            error={errors.status?.message}
            {...register('status')}
          />
          <Input
            label="Phone"
            error={errors.phone?.message}
            {...register('phone')}
          />
          <Input
            label="Email"
            type="email"
            error={errors.email?.message}
            {...register('email')}
          />
          <Input
            label="Timezone"
            placeholder="Asia/Dhaka"
            error={errors.timezone?.message}
            {...register('timezone')}
          />

          <div className="sm:col-span-2">
            <Input
              label="Address line 1"
              error={errors.address_line1?.message}
              {...register('address_line1')}
            />
          </div>
          <div className="sm:col-span-2">
            <Input
              label="Address line 2"
              error={errors.address_line2?.message}
              {...register('address_line2')}
            />
          </div>

          <Input
            label="City"
            error={errors.city?.message}
            {...register('city')}
          />
          <Input
            label="District"
            error={errors.district?.message}
            {...register('district')}
          />
          <Input
            label="Division"
            error={errors.division?.message}
            {...register('division')}
          />
          <Input
            label="Postal code"
            error={errors.postal_code?.message}
            {...register('postal_code')}
          />
          <Input
            label="Country"
            placeholder="BD"
            maxLength={2}
            error={errors.country?.message}
            {...register('country')}
          />

          <Input
            label="Latitude"
            type="number"
            step="any"
            min="-90"
            max="90"
            error={errors.latitude?.message}
            {...register('latitude')}
          />
          <Input
            label="Longitude"
            type="number"
            step="any"
            min="-180"
            max="180"
            error={errors.longitude?.message}
            {...register('longitude')}
          />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button size="sm" type="submit" loading={isSubmitting || save.isPending}>
            {branch ? 'Update' : 'Create'}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
