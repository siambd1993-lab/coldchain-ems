import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { customersApi } from '@/api/customers'
import { Modal, ModalFooter, Button, Input, Select, Textarea } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import { takaToPoisha, poishaToTaka } from '@/utils/format'
import type { Customer } from '@/types'

const schema = z.object({
  code:              z.string().min(1, 'Required').max(30),
  name:              z.string().min(1, 'Required'),
  type:              z.enum(['individual', 'business']),
  email:             z.string().email().optional().or(z.literal('')),
  phone:             z.string().optional(),
  address_line1:     z.string().optional(),
  credit_limit_taka: z.string().optional(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  open:      boolean
  onClose:   () => void
  customer?: Customer | null
}

export function CustomerModal({ open, onClose, customer }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { type: 'individual', credit_limit_taka: '0' },
  })

  useEffect(() => {
    if (open) {
      if (customer) {
        reset({
          code:              customer.code,
          name:              customer.name,
          type:              customer.type,
          email:             customer.email ?? '',
          phone:             customer.phone ?? '',
          address_line1:     customer.address_line1 ?? '',
          credit_limit_taka: poishaToTaka(customer.credit_limit_poisha ?? 0),
        })
      } else {
        // Auto-generate a customer code suggestion
        const ts = Date.now().toString(36).toUpperCase()
        reset({ code: `CUST-${ts}`, type: 'individual', credit_limit_taka: '0' })
      }
    }
  }, [open, customer, reset])

  const save = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = {
        code:                values.code,
        name:                values.name,
        type:                values.type,
        email:               values.email || undefined,
        phone:               values.phone || undefined,
        address_line1:       values.address_line1 || undefined,
        credit_limit_poisha: takaToPoisha(values.credit_limit_taka ?? '0'),
      }
      return customer
        ? customersApi.update(customer.id, payload)
        : customersApi.create(payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['customers'] })
      success(customer ? 'Customer updated' : 'Customer created')
      onClose()
    },
    onError: apiError,
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={customer ? 'Edit Customer' : 'New Customer'}
      size="lg"
    >
      <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            label="Customer code *"
            placeholder="CUST-XXXXX"
            hint={customer ? 'Code cannot be changed after creation' : 'Auto-generated — you may override'}
            error={errors.code?.message}
            readOnly={!!customer}
            {...register('code')}
          />
          <Input
            label="Full name *"
            error={errors.name?.message}
            {...register('name')}
          />
          <Select
            label="Type"
            options={[
              { value: 'individual', label: 'Individual' },
              { value: 'business',   label: 'Business'   },
            ]}
            error={errors.type?.message}
            {...register('type')}
          />
          <Input
            label="Email"
            type="email"
            error={errors.email?.message}
            {...register('email')}
          />
          <Input
            label="Phone"
            error={errors.phone?.message}
            {...register('phone')}
          />
          <Input
            label="Credit limit (BDT)"
            type="number"
            min="0"
            step="0.01"
            prefix="৳"
            error={errors.credit_limit_taka?.message}
            {...register('credit_limit_taka')}
          />
          <div className="sm:col-span-2">
            <Textarea
              label="Address"
              rows={2}
              error={errors.address_line1?.message}
              {...register('address_line1')}
            />
          </div>
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button size="sm" type="submit" loading={isSubmitting || save.isPending}>
            {customer ? 'Update' : 'Create'}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
