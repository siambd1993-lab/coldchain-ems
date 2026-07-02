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
  reason:    z.string().min(1, 'Reason is required for adjustments'),
  reference: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props { open: boolean; onClose: () => void; lot: StockLot | null }

export function AdjustModal({ open, onClose, lot }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
  })

  useEffect(() => { if (open) reset({}) }, [open, reset])

  const adjust = useMutation({
    mutationFn: (v: FormValues) =>
      inventoryApi.adjust(lot!.id, {
        delta:     v.quantity,
        reason:    v.reason,
        reference: v.reference || undefined,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['lots'] })
      success('Adjustment recorded')
      onClose()
    },
    onError: apiError,
  })

  if (!lot) return null

  return (
    <Modal open={open} onClose={onClose} title="Adjust Stock" size="md">
      <p className="mb-4 text-sm text-gray-500">
        Lot <strong>{lot.lot_code}</strong> — current:{' '}
        <strong>{formatQuantity(lot.quantity, lot.unit_of_measure)}</strong>
      </p>
      <form onSubmit={handleSubmit((v) => adjust.mutate(v))} noValidate>
        <div className="flex flex-col gap-4">
          <Input
            label="Quantity adjustment *"
            type="number"
            step="0.001"
            placeholder="+50 or -10"
            suffix={lot.unit_of_measure}
            hint="Use positive to add, negative to subtract"
            error={errors.quantity?.message}
            {...register('quantity')}
          />
          <Input label="Reference" placeholder="Document #" {...register('reference')} />
          <Textarea
            label="Reason *"
            rows={2}
            error={errors.reason?.message}
            {...register('reason')}
          />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" type="button" onClick={onClose}>Cancel</Button>
          <Button size="sm" type="submit" loading={isSubmitting || adjust.isPending}>Apply Adjustment</Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
