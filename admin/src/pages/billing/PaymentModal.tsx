import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { billingApi }   from '@/api/billing'
import { customersApi } from '@/api/customers'
import { Modal, ModalFooter, Button, Input, Select, Textarea } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import { takaToPoisha } from '@/utils/format'

const METHODS = ['cash', 'bank_transfer', 'cheque', 'bkash', 'nagad', 'rocket', 'card', 'other']

const schema = z.object({
  customer_id: z.string().min(1, 'Required'),
  amount_taka: z.string().min(1, 'Required'),
  method:      z.string().min(1, 'Required'),
  paid_at:     z.string().min(1, 'Required'),
  reference:   z.string().optional(),
  notes:       z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props { open: boolean; onClose: () => void }

export function PaymentModal({ open, onClose }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { data: customers } = useQuery({
    queryKey: ['customers-all'],
    queryFn:  () => customersApi.list({ per_page: 200 }),
    enabled:  open,
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      method:  'cash',
      paid_at: new Date().toISOString().substring(0, 16),
    },
  })

  useEffect(() => {
    if (open) {
      reset({
        method:  'cash',
        paid_at: new Date().toISOString().substring(0, 16),
      })
    }
  }, [open, reset])

  const create = useMutation({
    mutationFn: (v: FormValues) =>
      billingApi.createPayment({
        customer_id:   Number(v.customer_id),
        amount_poisha: takaToPoisha(v.amount_taka),
        method:        v.method,
        paid_at:       v.paid_at,
        reference:     v.reference || undefined,
        notes:         v.notes     || undefined,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['payments'] })
      success('Payment recorded')
      onClose()
    },
    onError: apiError,
  })

  const customerOpts = customers?.data.map((c) => ({ value: c.id, label: c.name })) ?? []
  const methodOpts   = METHODS.map((m) => ({
    value: m,
    label: m.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
  }))

  return (
    <Modal open={open} onClose={onClose} title="Record Payment" size="md">
      <form onSubmit={handleSubmit((v) => create.mutate(v))} noValidate>
        <div className="flex flex-col gap-4">
          <Select
            label="Customer *"
            options={customerOpts}
            placeholder="Select customer…"
            error={errors.customer_id?.message}
            {...register('customer_id')}
          />
          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Amount (BDT) *"
              type="number"
              step="0.01"
              min="0.01"
              prefix="৳"
              error={errors.amount_taka?.message}
              {...register('amount_taka')}
            />
            <Select
              label="Method *"
              options={methodOpts}
              error={errors.method?.message}
              {...register('method')}
            />
          </div>
          <Input
            label="Paid at *"
            type="datetime-local"
            error={errors.paid_at?.message}
            {...register('paid_at')}
          />
          <Input label="Reference / cheque #" {...register('reference')} />
          <Textarea label="Notes" rows={2} {...register('notes')} />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" type="button" onClick={onClose}>Cancel</Button>
          <Button size="sm" type="submit" loading={isSubmitting || create.isPending}>Record</Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
