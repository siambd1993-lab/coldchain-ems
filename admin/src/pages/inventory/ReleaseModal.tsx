import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { inventoryApi } from '@/api/inventory'
import { Modal, ModalFooter, Button, Input, Textarea } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import { formatQuantity } from '@/utils/format'
import type { StockLot } from '@/types'

const schema = z.object({
  quantity:  z.string().min(1, 'Required'),
  reason:    z.string().optional(),
  reference: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props { open: boolean; onClose: () => void; lot: StockLot | null }

export function ReleaseModal({ open, onClose, lot }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
  })

  useEffect(() => { if (open) reset({}) }, [open, reset])

  const release = useMutation({
    mutationFn: (v: FormValues) =>
      inventoryApi.release(lot!.id, {
        quantity:  v.quantity,
        reason:    v.reason || undefined,
        reference: v.reference || undefined,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['lots'] })
      success('Release recorded')
      onClose()
    },
    onError: apiError,
  })

  if (!lot) return null

  return (
    <Modal open={open} onClose={onClose} title="Release Stock" size="md">
      <p className="mb-4 text-sm text-gray-500">
        Lot <strong>{lot.lot_code}</strong> — available:{' '}
        <strong>{formatQuantity(lot.quantity, lot.unit_of_measure)}</strong>
      </p>
      <form onSubmit={handleSubmit((v) => release.mutate(v))} noValidate>
        <div className="flex flex-col gap-4">
          <Input
            label="Quantity to release *"
            type="number"
            step="0.001"
            min="0.001"
            max={String(lot.quantity)}
            suffix={lot.unit_of_measure}
            error={errors.quantity?.message}
            {...register('quantity')}
          />
          <Input label="Reference" placeholder="Delivery order #" {...register('reference')} />
          <Textarea label="Reason" rows={2} {...register('reason')} />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" type="button" onClick={onClose}>Cancel</Button>
          <Button size="sm" type="submit" loading={isSubmitting || release.isPending}>Release</Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
