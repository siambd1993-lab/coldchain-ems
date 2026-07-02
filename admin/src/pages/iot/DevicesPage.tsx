import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Plus, Pencil, Trash2, Wifi, WifiOff, Cpu } from 'lucide-react'
import { devicesApi } from '@/api/iot'
import { chambersApi } from '@/api/chambers'
import { useUiStore } from '@/stores/ui'
import {
  Button, Input, Select, Card, CardHeader, CardTitle, Modal, ModalFooter,
  Table, TableHead, TableBody, TableRow, TableTh, TableTd, TableEmpty, TableError,
  Pagination, ConfirmDialog, Badge,
} from '@/components/ui'
import { useToast }   from '@/hooks/useToast'
import { useConfirm } from '@/hooks/useConfirm'
import type { Device } from '@/types'

const STATUS_VARIANT: Record<Device['status'], 'green' | 'red' | 'yellow' | 'default'> = {
  online: 'green', offline: 'red', fault: 'red', provisioning: 'yellow', decommissioned: 'default',
}

const TYPES = ['sensor', 'gateway', 'plc', 'bms', 'inverter', 'energy_meter', 'controller']
const PROTOCOLS = ['mqtt', 'modbus_tcp', 'modbus_rtu', 'rs485', 'http', 'snmp']

const schema = z.object({
  device_uid:  z.string().min(1, 'Required').max(100),
  name:        z.string().min(1, 'Required').max(255),
  device_type: z.string().min(1),
  protocol:    z.string().optional(),
  chamber_id:  z.string().optional(),
  model:       z.string().optional(),
  manufacturer: z.string().optional(),
  status:      z.string().optional(),
})
type FormValues = z.infer<typeof schema>

export function DevicesPage() {
  const qc = useQueryClient()
  const { success, apiError } = useToast()
  const { confirm, confirmProps } = useConfirm()
  const activeBranchId = useUiStore((s) => s.activeBranchId)

  const [page, setPage] = useState(1)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Device | null>(null)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['devices', { page }],
    queryFn:  () => devicesApi.list({ page, per_page: 20 }),
  })

  const { data: chambers } = useQuery({
    queryKey: ['chambers-all'],
    queryFn:  () => chambersApi.list({ per_page: 100 }),
    staleTime: 60_000,
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } =
    useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (modalOpen) {
      if (editing) {
        reset({
          device_uid:   editing.device_uid,
          name:         editing.name,
          device_type:  editing.device_type,
          protocol:     editing.protocol ?? '',
          chamber_id:   editing.chamber_id ? String(editing.chamber_id) : '',
          model:        editing.model ?? '',
          manufacturer: editing.manufacturer ?? '',
          status:       editing.status,
        })
      } else {
        reset({ device_type: 'sensor', protocol: 'mqtt' })
      }
    }
  }, [modalOpen, editing, reset])

  const save = useMutation({
    mutationFn: (v: FormValues) => {
      const common = {
        name:         v.name,
        device_type:  v.device_type,
        protocol:     v.protocol || undefined,
        chamber_id:   v.chamber_id ? Number(v.chamber_id) : null,
        model:        v.model || undefined,
        manufacturer: v.manufacturer || undefined,
      }
      return editing
        ? devicesApi.update(editing.id, { ...common, status: v.status })
        : devicesApi.create({ ...common, device_uid: v.device_uid, branch_id: activeBranchId! })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['devices'] })
      success(editing ? 'Device updated' : 'Device registered')
      setModalOpen(false)
    },
    onError: apiError,
  })

  const destroy = useMutation({
    mutationFn: (id: number) => devicesApi.destroy(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['devices'] })
      success('Device removed')
    },
    onError: apiError,
  })

  async function handleDelete(d: Device) {
    const ok = await confirm({ title: `Remove ${d.name}?`, danger: true })
    if (ok) destroy.mutate(d.id)
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">IoT Devices</h1>
          <p className="text-sm text-gray-500">
            Sensors, meters and controllers — live ingestion activates with the MQTT broker (VPS)
          </p>
        </div>
        <Button size="sm" onClick={() => { setEditing(null); setModalOpen(true) }}>
          <Plus className="h-4 w-4" /> Register Device
        </Button>
      </div>

      <Card>
        <CardHeader><CardTitle>Registry</CardTitle></CardHeader>
        <Table>
          <TableHead>
            <tr>
              <TableTh>Device</TableTh>
              <TableTh>Type</TableTh>
              <TableTh>Chamber</TableTh>
              <TableTh>Status</TableTh>
              <TableTh>Readings</TableTh>
              <TableTh>Last Seen</TableTh>
              <TableTh />
            </tr>
          </TableHead>
          <TableBody>
            {isLoading && (
              <tr><td colSpan={7} className="py-10 text-center text-sm text-gray-400">Loading…</td></tr>
            )}
            {isError && <TableError colSpan={7} error={error} />}
            {!isLoading && !isError && data?.data.length === 0 && (
              <TableEmpty colSpan={7} message="No devices registered yet." />
            )}
            {data?.data.map((d) => (
              <TableRow key={d.id}>
                <TableTd>
                  <div className="flex items-center gap-2">
                    <Cpu className="h-4 w-4 text-gray-300" />
                    <div>
                      <p className="font-medium text-gray-900">{d.name}</p>
                      <p className="text-xs text-gray-400">{d.device_uid}{d.model ? ` · ${d.model}` : ''}</p>
                    </div>
                  </div>
                </TableTd>
                <TableTd className="text-gray-500">{d.device_type.replace(/_/g, ' ')}</TableTd>
                <TableTd className="text-gray-500">{d.chamber?.name ?? '—'}</TableTd>
                <TableTd>
                  <Badge variant={STATUS_VARIANT[d.status]}>
                    {d.status === 'online'
                      ? <Wifi className="mr-1 h-3 w-3" />
                      : <WifiOff className="mr-1 h-3 w-3" />}
                    {d.status}
                  </Badge>
                </TableTd>
                <TableTd>
                  <div className="flex flex-wrap gap-1.5">
                    {(d.channels ?? []).slice(0, 3).map((ch) => (
                      <span key={ch.id} className="rounded bg-gray-50 px-1.5 py-0.5 text-xs tabular-nums text-gray-600 ring-1 ring-gray-100">
                        {ch.label ?? ch.metric}: <strong>{ch.last_value ?? '—'}{ch.unit ?? ''}</strong>
                      </span>
                    ))}
                  </div>
                </TableTd>
                <TableTd className="whitespace-nowrap text-gray-400">
                  {d.last_seen_at ? new Date(d.last_seen_at).toLocaleTimeString() : 'never'}
                </TableTd>
                <TableTd>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" onClick={() => { setEditing(d); setModalOpen(true) }}>
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button
                      variant="ghost" size="icon"
                      className="text-red-400 hover:text-red-600"
                      onClick={() => handleDelete(d)}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </TableTd>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        {data?.meta && (
          <Pagination meta={data.meta} onPage={setPage} className="border-t border-gray-100 px-4" />
        )}
      </Card>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? `Edit ${editing.name}` : 'Register Device'}
        size="lg"
      >
        <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input
              label="Hardware UID *"
              placeholder="e.g. SENS-BR1-05"
              readOnly={!!editing}
              hint={editing ? 'UID cannot change' : 'Unique identifier printed on the device'}
              error={errors.device_uid?.message}
              {...register('device_uid')}
            />
            <Input label="Name *" error={errors.name?.message} {...register('name')} />
            <Select
              label="Type *"
              options={TYPES.map((t) => ({ value: t, label: t.replace(/_/g, ' ') }))}
              {...register('device_type')}
            />
            <Select
              label="Protocol"
              options={PROTOCOLS.map((p) => ({ value: p, label: p }))}
              {...register('protocol')}
            />
            <Select
              label="Chamber"
              options={[
                { value: '', label: '— none (branch-level) —' },
                ...(chambers?.data.map((c) => ({ value: String(c.id), label: c.name })) ?? []),
              ]}
              {...register('chamber_id')}
            />
            {editing && (
              <Select
                label="Status"
                options={['provisioning', 'online', 'offline', 'fault', 'decommissioned']
                  .map((s) => ({ value: s, label: s }))}
                {...register('status')}
              />
            )}
            <Input label="Model" {...register('model')} />
            <Input label="Manufacturer" {...register('manufacturer')} />
          </div>
          <ModalFooter className="-mx-5 mt-4">
            <Button variant="outline" size="sm" type="button" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button size="sm" type="submit" loading={isSubmitting || save.isPending}>
              {editing ? 'Update' : 'Register'}
            </Button>
          </ModalFooter>
        </form>
      </Modal>

      <ConfirmDialog {...confirmProps} />
    </div>
  )
}
