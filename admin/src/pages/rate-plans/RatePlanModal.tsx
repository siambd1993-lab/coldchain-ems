import { useEffect } from 'react'
import { useForm }   from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }          from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { billingApi } from '@/api/billing'
import { Modal, ModalFooter, Button, Input, Select, Textarea } from '@/components/ui'
import { useToast } from '@/hooks/useToast'
import { takaToPoisha, poishaToTaka } from '@/utils/format'
import type { RatePlan } from '@/types'

const METHODS = [
  { value: 'per_kg_per_day',      label: 'Per kg / day'      },
  { value: 'per_kg_per_month',    label: 'Per kg / month'    },
  { value: 'per_slot_per_month',  label: 'Per slot / month'  },
  { value: 'flat_monthly',        label: 'Flat monthly'      },
]

const schema = z.object({
  name:           z.string().min(1, 'Required'),
  description:    z.string().optional(),
  billing_method: z.string().min(1, 'Required'),
  rate_taka:      z.string().min(1, 'Required'),
  tax_rate:       z.string().optional(),
  is_active:      z.string(),
})
type FormValues = z.infer<typeof schema>

interface Props {
  open:      boolean
  onClose:   () => void
  ratePlan?: RatePlan | null
}

export function RatePlanModal({ open, onClose, ratePlan }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      billing_method: 'per_kg_per_month',
      tax_rate:       '0.15',
      is_active:      'true',
    },
  })

  useEffect(() => {
    if (open) {
      reset(
        ratePlan
          ? {
              name:           ratePlan.name,
              description:    (ratePlan as any).description ?? '',
              billing_method: ratePlan.billing_method,
              rate_taka:      poishaToTaka(ratePlan.rate_poisha),
              tax_rate:       String(ratePlan.tax_rate ?? 0.15),
              is_active:      String(ratePlan.is_active),
            }
          : {
              billing_method: 'per_kg_per_month',
              tax_rate: '0.15',
              is_active: 'true',
            },
      )
    }
  }, [open, ratePlan, reset])

  const save = useMutation({
    mutationFn: (v: FormValues) => {
      const payload = {
        name:           v.name,
        description:    v.description || undefined,
        billing_method: v.billing_method,
        rate_poisha:    takaToPoisha(v.rate_taka),
        tax_rate:       v.tax_rate ? Number(v.tax_rate) : 0,
        is_active:      v.is_active === 'true',
      }
      return ratePlan
        ? billingApi.updateRatePlan(ratePlan.id, payload)
        : billingApi.createRatePlan(payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['rate-plans'] })
      success(ratePlan ? 'Rate plan updated' : 'Rate plan created')
      onClose()
    },
    onError: apiError,
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={ratePlan ? 'Edit Rate Plan' : 'New Rate Plan'}
      size="md"
    >
      <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
        <div className="flex flex-col gap-4">
          <Input
            label="Name *"
            error={errors.name?.message}
            {...register('name')}
          />
          <Textarea label="Description" rows={2} {...register('description')} />
          <Select
            label="Billing method *"
            options={METHODS}
            error={errors.billing_method?.message}
            {...register('billing_method')}
          />
          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Rate (BDT) *"
              type="number"
              step="0.01"
              min="0"
              prefix="৳"
              error={errors.rate_taka?.message}
              {...register('rate_taka')}
            />
            <Input
              label="Tax rate"
              type="number"
              step="0.01"
              min="0"
              max="1"
              placeholder="0.15"
              hint="Enter as decimal: 0.15 = 15%"
              {...register('tax_rate')}
            />
          </div>
          <Select
            label="Status"
            options={[
              { value: 'true',  label: 'Active'   },
              { value: 'false', label: 'Inactive' },
            ]}
            {...register('is_active')}
          />
        </div>
        <ModalFooter className="-mx-5 mt-4">
          <Button variant="outline" size="sm" type="button" onClick={onClose}>Cancel</Button>
          <Button size="sm" type="submit" loading={isSubmitting || save.isPending}>
            {ratePlan ? 'Update' : 'Create'}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  )
}
