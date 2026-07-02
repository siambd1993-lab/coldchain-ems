import { useQuery } from '@tanstack/react-query'
import {
  Users,
  Warehouse,
  Package,
  Receipt,
} from 'lucide-react'
import { customersApi } from '@/api/customers'
import { chambersApi }  from '@/api/chambers'
import { inventoryApi } from '@/api/inventory'
import { billingApi }   from '@/api/billing'
import { StatCard, Card, CardHeader, CardTitle, CardContent, PageSpinner } from '@/components/ui'
import { formatMoney, formatQuantity } from '@/utils/format'

export function Dashboard() {
  const { data: customers } = useQuery({
    queryKey: ['customers', 'summary'],
    queryFn:  () => customersApi.list({ per_page: 1 }),
  })
  const { data: chambers } = useQuery({
    queryKey: ['chambers', 'summary'],
    queryFn:  () => chambersApi.list({ per_page: 1 }),
  })
  const { data: lots } = useQuery({
    queryKey: ['lots', 'summary'],
    queryFn:  () => inventoryApi.listLots({ per_page: 5 }),
  })
  const { data: invoices } = useQuery({
    queryKey: ['invoices', 'summary'],
    queryFn:  () => billingApi.listInvoices({ per_page: 5, status: 'issued' }),
  })

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold text-gray-900">Dashboard</h1>
        <p className="text-sm text-gray-500">Welcome back — here's an overview of your cold storage operations.</p>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total Customers"
          value={customers?.meta.total ?? '—'}
          icon={<Users className="h-5 w-5" />}
        />
        <StatCard
          label="Active Chambers"
          value={chambers?.meta.total ?? '—'}
          icon={<Warehouse className="h-5 w-5" />}
        />
        <StatCard
          label="Stock Lots"
          value={lots?.meta.total ?? '—'}
          icon={<Package className="h-5 w-5" />}
        />
        <StatCard
          label="Outstanding Invoices"
          value={invoices?.meta.total ?? '—'}
          icon={<Receipt className="h-5 w-5" />}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Recent Invoices */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Issued Invoices</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {!invoices ? (
              <div className="flex h-40 items-center justify-center">
                <PageSpinner />
              </div>
            ) : (
              <ul className="divide-y divide-gray-100">
                {invoices.data.length === 0 && (
                  <li className="py-10 text-center text-sm text-gray-400">No invoices found.</li>
                )}
                {invoices.data.map((inv) => (
                  <li key={inv.id} className="flex items-center justify-between px-5 py-3">
                    <div>
                      <p className="text-sm font-medium text-gray-900">{inv.invoice_number}</p>
                      <p className="text-xs text-gray-500">{inv.customer?.name}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-gray-900">
                        {formatMoney(inv.total_poisha)}
                      </p>
                      <p className="text-xs text-gray-400 capitalize">{inv.status}</p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        {/* Recent Lots */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Stock Lots</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {!lots ? (
              <div className="flex h-40 items-center justify-center">
                <PageSpinner />
              </div>
            ) : (
              <ul className="divide-y divide-gray-100">
                {lots.data.length === 0 && (
                  <li className="py-10 text-center text-sm text-gray-400">No lots found.</li>
                )}
                {lots.data.map((lot) => (
                  <li key={lot.id} className="flex items-center justify-between px-5 py-3">
                    <div>
                      <p className="text-sm font-medium text-gray-900">{lot.lot_code}</p>
                      <p className="text-xs text-gray-500">{lot.product?.name}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-semibold text-gray-900">
                        {formatQuantity(lot.quantity, lot.unit_of_measure)}
                      </p>
                      <p className="text-xs text-gray-400 capitalize">
                        {lot.status?.replace(/_/g, ' ')}
                      </p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
