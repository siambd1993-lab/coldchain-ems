import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useState }      from 'react'
import { useForm }       from 'react-hook-form'
import { zodResolver }   from '@hookform/resolvers/zod'
import { z }             from 'zod'
import { billingApi }    from '@/api/billing'
import {
  Modal, ModalFooter, Button, Input, Spinner,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty,
  InvoiceStatusBadge,
} from '@/components/ui'
import { formatMoney, formatDate } from '@/utils/format'
import { useToast } from '@/hooks/useToast'
import type { Invoice } from '@/types'

const lineSchema = z.object({
  description:       z.string().min(1, 'Required'),
  quantity:          z.string().min(1, 'Required'),
  unit_price_poisha: z.string().min(1, 'Required'),
  tax_rate:          z.string().optional(),
})
type LineForm = z.infer<typeof lineSchema>

interface Props {
  invoice: Invoice | null
  onClose: () => void
}

export function InvoiceDetail({ invoice, onClose }: Props) {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const [showLineForm, setShowLineForm] = useState(false)

  const { data, isLoading } = useQuery({
    queryKey: ['invoice', invoice?.id],
    queryFn:  () => billingApi.showInvoice(invoice!.id),
    enabled:  !!invoice,
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<LineForm>({
    resolver: zodResolver(lineSchema),
    defaultValues: { tax_rate: '0.15' },
  })

  const addLine = useMutation({
    mutationFn: (v: LineForm) =>
      billingApi.addLine(invoice!.id, {
        description:       v.description,
        quantity:          v.quantity,
        unit_price_poisha: Number(v.unit_price_poisha),
        tax_rate:          v.tax_rate ? Number(v.tax_rate) : 0.15,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['invoice', invoice?.id] })
      qc.invalidateQueries({ queryKey: ['invoices'] })
      success('Line added')
      reset()
      setShowLineForm(false)
    },
    onError: apiError,
  })

  const removeLine = useMutation({
    mutationFn: (lineId: number) => billingApi.removeLine(invoice!.id, lineId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['invoice', invoice?.id] })
      qc.invalidateQueries({ queryKey: ['invoices'] })
      success('Line removed')
    },
    onError: apiError,
  })

  const inv = data ?? invoice

  return (
    <Modal open={!!invoice} onClose={onClose} title="Invoice Details" size="2xl">
      {isLoading && <div className="flex h-40 items-center justify-center"><Spinner size="md" /></div>}
      {inv && (
        <div className="space-y-4">
          {/* Header */}
          <div className="flex items-start justify-between">
            <div>
              <p className="text-lg font-bold text-gray-900">{inv.invoice_number}</p>
              <p className="text-sm text-gray-500">{inv.customer?.name}</p>
            </div>
            <InvoiceStatusBadge status={inv.status} />
          </div>

          <div className="grid grid-cols-3 gap-3 text-sm">
            <div>
              <p className="text-gray-500">Issue date</p>
              <p className="font-medium">{formatDate(inv.issued_at ?? inv.issue_date)}</p>
            </div>
            <div>
              <p className="text-gray-500">Due date</p>
              <p className="font-medium">{formatDate(inv.due_date)}</p>
            </div>
            <div>
              <p className="text-gray-500">Period</p>
              <p className="font-medium">
                {inv.period?.start
                  ? `${formatDate(inv.period.start)} – ${formatDate(inv.period.end)}`
                  : '—'}
              </p>
            </div>
          </div>

          {/* Lines */}
          <div>
            <div className="mb-2 flex items-center justify-between">
              <p className="text-sm font-semibold text-gray-700">Line Items</p>
              {inv.status === 'draft' && (
                <Button
                  variant="ghost"
                  size="xs"
                  onClick={() => setShowLineForm((s) => !s)}
                >
                  <Plus className="h-3.5 w-3.5" /> Add line
                </Button>
              )}
            </div>

            {showLineForm && inv.status === 'draft' && (
              <form
                onSubmit={handleSubmit((v) => addLine.mutate(v))}
                className="mb-3 grid grid-cols-4 gap-2 rounded-lg border border-blue-100 bg-blue-50 p-3"
              >
                <Input
                  placeholder="Description *"
                  error={errors.description?.message}
                  className="col-span-2"
                  {...register('description')}
                />
                <Input placeholder="Qty *" type="number" step="0.001" error={errors.quantity?.message} {...register('quantity')} />
                <Input placeholder="Unit price (poisha) *" type="number" error={errors.unit_price_poisha?.message} {...register('unit_price_poisha')} />
                <div className="col-span-4 flex items-center justify-end gap-2">
                  <Input placeholder="Tax rate (0.15)" className="w-32" {...register('tax_rate')} />
                  <Button size="xs" type="submit" loading={isSubmitting || addLine.isPending}>Add</Button>
                  <Button size="xs" variant="ghost" type="button" onClick={() => setShowLineForm(false)}>Cancel</Button>
                </div>
              </form>
            )}

            <Table>
              <TableHead>
                <tr>
                  <TableTh>Description</TableTh>
                  <TableTh className="text-right">Qty</TableTh>
                  <TableTh className="text-right">Unit Price</TableTh>
                  <TableTh className="text-right">Tax</TableTh>
                  <TableTh className="text-right">Total</TableTh>
                  {inv.status === 'draft' && <TableTh />}
                </tr>
              </TableHead>
              <TableBody>
                {(inv.lines ?? []).length === 0 && (
                  <TableEmpty colSpan={inv.status === 'draft' ? 6 : 5} message="No line items yet." />
                )}
                {(inv.lines ?? []).map((line) => (
                  <TableRow key={line.id}>
                    <TableTd>{line.description}</TableTd>
                    <TableTd className="text-right tabular-nums">{line.quantity}</TableTd>
                    <TableTd className="text-right tabular-nums">{formatMoney(line.unit_price_poisha)}</TableTd>
                    <TableTd className="text-right text-gray-400">
                      {line.tax_rate != null ? `${(Number(line.tax_rate) * 100).toFixed(0)}%` : '—'}
                    </TableTd>
                    <TableTd className="text-right font-medium tabular-nums">{formatMoney(line.amount_poisha)}</TableTd>
                    {inv.status === 'draft' && (
                      <TableTd>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-red-400 hover:text-red-600"
                          onClick={() => removeLine.mutate(line.id)}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </TableTd>
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            <div className="mt-3 flex flex-col items-end gap-1 text-sm">
              <div className="flex gap-8">
                <span className="text-gray-500">Subtotal</span>
                <span className="tabular-nums">{formatMoney(inv.subtotal_poisha)}</span>
              </div>
              <div className="flex gap-8">
                <span className="text-gray-500">Tax</span>
                <span className="tabular-nums">{formatMoney(inv.tax_poisha)}</span>
              </div>
              <div className="flex gap-8 text-base font-bold text-gray-900">
                <span>Total</span>
                <span className="tabular-nums">{formatMoney(inv.total_poisha)}</span>
              </div>
              <div className="flex gap-8 text-sm text-gray-500">
                <span>Paid</span>
                <span className="tabular-nums">{formatMoney(inv.amount_paid_poisha)}</span>
              </div>
              <div className="flex gap-8 text-sm font-semibold text-orange-700">
                <span>Outstanding</span>
                <span className="tabular-nums">{formatMoney(inv.amount_due_poisha)}</span>
              </div>
            </div>
          </div>
        </div>
      )}
      <ModalFooter className="-mx-5 mt-2">
        <Button variant="outline" size="sm" onClick={onClose}>Close</Button>
      </ModalFooter>
    </Modal>
  )
}
