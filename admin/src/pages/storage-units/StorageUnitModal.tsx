import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { chambersApi, storageUnitsApi } from '@/api/chambers'
import { Modal, ModalFooter, Button, Input, Select } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import type { StorageUnit } from '@/types'

const UNIT_TYPE_OPTIONS = [
  { value: 'rack',            label: 'Rack'            },
  { value: 'shelf',           label: 'Shelf'           },
  { value: 'pallet_position', label: 'Pallet Position' },
  { value: 'bin',             label: 'Bin'             },
  { value: 'floor_space',     label: 'Floor Space'     },
  { value: 'room',            label: 'Room'            },
]

const STATUS_OPTIONS = [
  { value: 'available',   label: 'Available'   },
  { value: 'occupied',    label: 'Occupied'    },
  { value: 'reserved',    label: 'Reserved'    },
  { value: 'maintenance', label: 'Maintenance' },
]

const schema = z.object({
  chamber_id:         z.string().min(1, 'Required'),
  code:               z.string().min(1, 'Required').max(64),
  label:              z.string().optional(),
  unit_type:          z.enum(['rack', 'shelf', 'pallet_position', 'bin', 'floor_space', 'room']),
  status:             z.enum(['available', 'occupied', 'reserved', 'maintenance']),
  capacity_weight_kg: z.string().optional(),
  capacity_volume_m3: z.string().optional(),
  grid_row:           z.string().optional(),
  grid_column:        z.string().optional(),
  level:              z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  open:    boolean
  onClose: () => void
  unit?:   StorageUnit | null
  /** Pre-select a chamber when creating while the list is filtered to one. */
  defaultChamberId?: number
}

export function StorageUnitModal({ open, onClose, unit, defaultChamberId }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { data: chambers } = useQuery({
    queryKey: ['chambers-all'],
    queryFn:  () => chambersApi.list({ per_page: 100 }),
    enabled:  open,
  })

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { unit_type: 'rack', status: 'available' },
  })

  useEffect(() => {
    if (open) {
      if (unit) {
        reset({
          chamber_id:         String(unit.chamber_id),
          code:               unit.code,
          label:              unit.label ?? '',
          unit_type:          unit.unit_type,
          status:             unit.status,
          capacity_weight_kg: unit.capacity_weight_kg != null ? String(unit.capacity_weight_kg) : '',
          capacity_volume_m3: unit.capacity_volume_m3 != null ? String(unit.capacity_volume_m3) : '',
          grid_row:           unit.grid_row ?? '',
          grid_column:        unit.grid_column ?? '',
          level:              unit.level ?? '',
        })
      } else {
        reset({
          chamber_id: defaultChamberId ? String(defaultChamberId) : '',
          unit_type:  'rack',
          status:     'available',
        })
      }
    }
  }, [open, unit, defaultChamberId, reset])

  const save = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = {
        chamber_id:         Number(values.chamber_id),
        code:               values.code,
        label:              values.label || undefined,
        unit_type:          values.unit_type,
        status:             values.status,
        capacity_weight_kg: values.capacity_weight_kg ? Number(values.capacity_weight_kg) : undefined,
        capacity_volume_m3: values.capacity_volume_m3 ? Number(values.capacity_volume_m3) : undefined,
        grid_row:           values.grid_row || undefined,
        grid_column:        values.grid_column || undefined,
        level:              values.level || undefined,
      }
      return unit
        ? storageUnitsApi.update(unit.id, payload)
        : storageUnitsApi.create(payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['storage-units'] })
      success(unit ? 'Storage unit updated' : 'Storage unit created')
      onClose()
    },
    onError: apiError,
  })

  const chamberOpts = chambers?.data.map((c) => ({ value: c.id, label: `${c.name} (${c.code})` })) ?? []

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={unit ? 'Edit Storage Unit' : 'New Storage Unit'}
      size="lg"
    >
      <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Select
            label="Chamber *"
            options={chamberOpts}
            placeholder="Select chamber…"
            hint={unit ? 'Chamber cannot be changed after creation' : undefined}
            disabled={!!unit}
            error={errors.chamber_id?.message}
            {...register('chamber_id')}
          />
          <Input
            label="Unit code *"
            placeholder="P01"
            error={errors.code?.message}
            {...register('code')}
          />
          <Input
            label="Label"
            placeholder="Pallet 1"
            error={errors.label?.message}
            {...register('label')}
          />
          <Select
            label="Unit type"
            options={UNIT_TYPE_OPTIONS}
            error={errors.unit_type?.message}
            {...register('unit_type')}
          />
          <Select
            label="Status"
            options={STATUS_OPTIONS}
            error={errors.status?.message}
            {...register('status')}
          />
          <Input
            label="Capacity (kg)"
            type="number"
            min="0"
            step="0.01"
            error={errors.capacity_weight_kg?.message}
            {...register('capacity_weight_kg')}
          />
          <Input
            label="Capacity (m³)"
            type="number"
            min="0"
            step="0.01"
            error={errors.capacity_volume_m3?.message}
            {...register('capacity_volume_m3')}
          />
          <Input
            label="Grid row"
            error={errors.grid_row?.message}
            {...register('grid_row')}
          />
          <Input
            label="Grid column"
            error={errors.grid_column?.message}
            {...register('grid_column')}
          />
          <Input
            label="Level"
            error={errors.level?.message}
            {...register('level')}
          />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button size="sm" type="submit" loading={isSubmitting || save.isPending}>
            {unit ? 'Update' : 'Create'}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
