import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { billingApi }   from '@/api/billing'
import { customersApi } from '@/api/customers'
import { Modal, ModalFooter, Button, Select, Input, Textarea } from '@/components/ui'
import { useToast } from '@/hooks/useToast'

const schema = z.object({
  customer_id:  z.string().min(1, 'Required'),
  issue_date:   z.string().min(1, 'Required'),
  period_start: z.string().optional(),
  period_end:   z.string().optional(),
  due_date:     z.string().optional(),
  notes:        z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props { open: boolean; onClose: () => void }

export function InvoiceModal({ open, onClose }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { data: customers } = useQuery({
    queryKey: ['customers-all'],
    queryFn:  () => customersApi.list({ per_page: 200 }),
    enabled:  open,
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
  })

  useEffect(() => {
    if (open) reset({ issue_date: new Date().toISOString().slice(0, 10) })
  }, [open, reset])

  const create = useMutation({
    mutationFn: (v: FormValues) =>
      billingApi.createInvoice({
        customer_id:  Number(v.customer_id),
        issue_date:   v.issue_date,
        period_start: v.period_start || undefined,
        period_end:   v.period_end   || undefined,
        due_date:     v.due_date     || undefined,
        notes:        v.notes        || undefined,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['invoices'] })
      success('Invoice draft created')
      onClose()
    },
    onError: apiError,
  })

  const customerOpts = customers?.data.map((c) => ({ value: c.id, label: c.name })) ?? []

  return (
    <Modal open={open} onClose={onClose} title="New Invoice (Draft)" size="md">
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
              label="Issue date *"
              type="date"
              error={errors.issue_date?.message}
              {...register('issue_date')}
            />
            <Input label="Due date" type="date" {...register('due_date')} />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Input label="Period start" type="date" {...register('period_start')} />
            <Input label="Period end"   type="date" {...register('period_end')} />
          </div>
          <Textarea label="Notes" rows={2} {...register('notes')} />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" type="button" onClick={onClose}>Cancel</Button>
          <Button size="sm" type="submit" loading={isSubmitting || create.isPending}>Create Draft</Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
