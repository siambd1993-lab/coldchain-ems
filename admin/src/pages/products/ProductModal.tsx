import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { productsApi } from '@/api/products'
import { Modal, ModalFooter, Button, Input, Select } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import type { Product, UnitOfMeasure } from '@/types'

const UNITS: UnitOfMeasure[] = ['kg', 'ton', 'crate', 'bag', 'carton', 'piece', 'pallet']

const schema = z.object({
  code:            z.string().min(1, 'Required').max(50),
  name:            z.string().min(1, 'Required').max(255),
  category:        z.string().max(100).optional(),
  unit_of_measure: z.enum(UNITS as [UnitOfMeasure, ...UnitOfMeasure[]]),
  temp_min:        z.string().optional(),
  temp_max:        z.string().optional(),
  shelf_life_days: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  open:     boolean
  onClose:  () => void
  product?: Product | null
}

export function ProductModal({ open, onClose, product }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { unit_of_measure: 'kg' },
  })

  useEffect(() => {
    if (open) {
      if (product) {
        reset({
          code:            product.code,
          name:            product.name,
          category:        product.category ?? '',
          unit_of_measure: product.unit_of_measure,
          temp_min:        product.default_temp_min_c != null ? String(product.default_temp_min_c) : '',
          temp_max:        product.default_temp_max_c != null ? String(product.default_temp_max_c) : '',
          shelf_life_days: product.shelf_life_days != null ? String(product.shelf_life_days) : '',
        })
      } else {
        const ts = Date.now().toString(36).toUpperCase()
        reset({ code: `PROD-${ts}`, unit_of_measure: 'kg' })
      }
    }
  }, [open, product, reset])

  const save = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = {
        code:                values.code,
        name:                values.name,
        category:            values.category || undefined,
        unit_of_measure:     values.unit_of_measure,
        default_temp_min_c:  values.temp_min ? parseFloat(values.temp_min) : undefined,
        default_temp_max_c:  values.temp_max ? parseFloat(values.temp_max) : undefined,
        shelf_life_days:     values.shelf_life_days ? parseInt(values.shelf_life_days, 10) : undefined,
      }
      return product
        ? productsApi.update(product.id, payload)
        : productsApi.create(payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['products'] })
      success(product ? 'Product updated' : 'Product created')
      onClose()
    },
    onError: apiError,
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={product ? 'Edit Product' : 'New Product'}
      size="lg"
    >
      <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            label="Product code *"
            placeholder="PROD-XXXXX"
            hint={product ? 'Code cannot be changed after creation' : 'Auto-generated — you may override'}
            error={errors.code?.message}
            readOnly={!!product}
            {...register('code')}
          />
          <Input
            label="Name *"
            placeholder="e.g. Potato (Diamond)"
            error={errors.name?.message}
            {...register('name')}
          />
          <Input
            label="Category"
            placeholder="e.g. Vegetables"
            error={errors.category?.message}
            {...register('category')}
          />
          <Select
            label="Unit of measure *"
            options={UNITS.map((u) => ({ value: u, label: u }))}
            error={errors.unit_of_measure?.message}
            {...register('unit_of_measure')}
          />
          <Input
            label="Storage temp — min (°C)"
            type="number"
            step="0.1"
            placeholder="e.g. 2"
            error={errors.temp_min?.message}
            {...register('temp_min')}
          />
          <Input
            label="Storage temp — max (°C)"
            type="number"
            step="0.1"
            placeholder="e.g. 8"
            error={errors.temp_max?.message}
            {...register('temp_max')}
          />
          <Input
            label="Shelf life (days)"
            type="number"
            min="1"
            placeholder="e.g. 180"
            error={errors.shelf_life_days?.message}
            {...register('shelf_life_days')}
          />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button size="sm" type="submit" loading={isSubmitting || save.isPending}>
            {product ? 'Update' : 'Create'}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
