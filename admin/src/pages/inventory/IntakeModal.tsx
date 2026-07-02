import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect }  from 'react'
import { inventoryApi } from '@/api/inventory'
import { customersApi } from '@/api/customers'
import { chambersApi, storageUnitsApi } from '@/api/chambers'
import { Modal, ModalFooter, Button, Input, Select, Textarea } from '@/components/ui'
import { useToast } from '@/hooks/useToast'

const UOM_OPTIONS = [
  { value: 'kg',      label: 'kg'      },
  { value: 'ton',     label: 'ton'     },
  { value: 'crate',   label: 'crate'   },
  { value: 'bag',     label: 'bag'     },
  { value: 'carton',  label: 'carton'  },
  { value: 'piece',   label: 'piece'   },
  { value: 'pallet',  label: 'pallet'  },
]

const schema = z.object({
  lot_code:        z.string().min(1, 'Required'),
  customer_id:     z.string().min(1, 'Required'),
  product_id:      z.string().optional(),
  chamber_id:      z.string().optional(),
  storage_unit_id: z.string().optional(),
  unit_of_measure: z.string().min(1, 'Required'),
  quantity:        z.string().min(1, 'Required'),
  received_at:     z.string().min(1, 'Required'),
  description:     z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props { open: boolean; onClose: () => void }

export function IntakeModal({ open, onClose }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { data: customers } = useQuery({
    queryKey: ['customers-all'],
    queryFn:  () => customersApi.list({ per_page: 200 }),
    enabled:  open,
  })
  const { data: products } = useQuery({
    queryKey: ['products-all'],
    queryFn:  () => inventoryApi.listProducts({ per_page: 200 }),
    enabled:  open,
  })
  const { data: chambers } = useQuery({
    queryKey: ['chambers-all'],
    queryFn:  () => chambersApi.list({ per_page: 100 }),
    enabled:  open,
  })

  const { register, handleSubmit, watch, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      unit_of_measure: 'kg',
      received_at: new Date().toISOString().substring(0, 16),
    },
  })

  const selectedChamberId = watch('chamber_id')

  const { data: units } = useQuery({
    queryKey: ['units', selectedChamberId],
    queryFn:  () => storageUnitsApi.list({ chamber_id: Number(selectedChamberId), per_page: 100 }),
    enabled:  !!selectedChamberId,
  })

  useEffect(() => {
    if (open) {
      // Auto-generate a lot code suggestion: LOT-YYYYMMDD-HHMMSS
      const now    = new Date()
      const stamp  = now.toISOString().replace(/[-:T]/g, '').substring(0, 14)
      const lot_code = `LOT-${stamp.substring(0, 8)}-${stamp.substring(8)}`
      reset({
        lot_code,
        unit_of_measure: 'kg',
        received_at: now.toISOString().substring(0, 16),
      })
    }
  }, [open, reset])

  const intake = useMutation({
    mutationFn: (v: FormValues) =>
      inventoryApi.intake({
        lot_code:        v.lot_code,
        customer_id:     Number(v.customer_id),
        product_id:      v.product_id ? Number(v.product_id) : undefined,
        chamber_id:      v.chamber_id ? Number(v.chamber_id) : undefined,
        storage_unit_id: v.storage_unit_id ? Number(v.storage_unit_id) : undefined,
        unit_of_measure: v.unit_of_measure as any,
        quantity:        v.quantity,
        received_at:     v.received_at,
        description:     v.description || undefined,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['lots'] })
      success('Intake recorded')
      onClose()
    },
    onError: apiError,
  })

  const customerOpts = customers?.data.map((c) => ({ value: c.id, label: c.name })) ?? []
  const productOpts  = products?.data.map((p) => ({ value: p.id, label: p.name })) ?? []
  const chamberOpts  = chambers?.data.map((c) => ({ value: c.id, label: c.name })) ?? []
  const unitOpts     = units?.data.map((u) => ({ value: u.id, label: `${u.code}${u.label ? ` — ${u.label}` : ''}` })) ?? []

  return (
    <Modal open={open} onClose={onClose} title="New Intake" size="lg">
      <form onSubmit={handleSubmit((v) => intake.mutate(v))} noValidate>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            label="Lot code *"
            placeholder="LOT-YYYYMMDD-HHMMSS"
            hint="Unique identifier for this stock lot"
            className="sm:col-span-2"
            error={errors.lot_code?.message}
            {...register('lot_code')}
          />
          <Select
            label="Customer *"
            options={customerOpts}
            placeholder="Select customer…"
            error={errors.customer_id?.message}
            {...register('customer_id')}
          />
          <Select
            label="Product"
            options={productOpts}
            placeholder="Select product…"
            {...register('product_id')}
          />
          <Select
            label="Chamber"
            options={chamberOpts}
            placeholder="Select chamber…"
            {...register('chamber_id')}
          />
          <Select
            label="Storage Unit"
            options={unitOpts}
            placeholder={selectedChamberId ? 'Optional…' : 'Select chamber first'}
            disabled={!selectedChamberId}
            {...register('storage_unit_id')}
          />
          <div className="grid grid-cols-2 gap-3 sm:col-span-2">
            <Input
              label="Quantity *"
              type="number"
              step="0.001"
              min="0.001"
              error={errors.quantity?.message}
              {...register('quantity')}
            />
            <Select
              label="Unit *"
              options={UOM_OPTIONS}
              error={errors.unit_of_measure?.message}
              {...register('unit_of_measure')}
            />
          </div>
          <Input
            label="Received at *"
            type="datetime-local"
            className="sm:col-span-2"
            error={errors.received_at?.message}
            {...register('received_at')}
          />
          <div className="sm:col-span-2">
            <Textarea label="Description" rows={2} {...register('description')} />
          </div>
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" type="button" onClick={onClose}>Cancel</Button>
          <Button size="sm" type="submit" loading={isSubmitting || intake.isPending}>Record Intake</Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
