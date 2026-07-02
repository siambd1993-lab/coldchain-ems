import { Routes, Route } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import { AppLayout }  from '@/layouts/AppLayout'
import { AuthLayout } from '@/layouts/AuthLayout'
import { PageSpinner } from '@/components/ui'

const Login          = lazy(() => import('@/pages/auth/Login').then(m => ({ default: m.Login })))
const Dashboard      = lazy(() => import('@/pages/dashboard/Dashboard').then(m => ({ default: m.Dashboard })))
const CustomersPage  = lazy(() => import('@/pages/customers/CustomersPage').then(m => ({ default: m.CustomersPage })))
const BranchesPage   = lazy(() => import('@/pages/branches/BranchesPage').then(m => ({ default: m.BranchesPage })))
const ChambersPage   = lazy(() => import('@/pages/chambers/ChambersPage').then(m => ({ default: m.ChambersPage })))
const StorageUnitsPage = lazy(() => import('@/pages/storage-units/StorageUnitsPage').then(m => ({ default: m.StorageUnitsPage })))
const InventoryPage  = lazy(() => import('@/pages/inventory/InventoryPage').then(m => ({ default: m.InventoryPage })))
const InvoicesPage   = lazy(() => import('@/pages/billing/InvoicesPage').then(m => ({ default: m.InvoicesPage })))
const PaymentsPage   = lazy(() => import('@/pages/billing/PaymentsPage').then(m => ({ default: m.PaymentsPage })))
const RatePlansPage  = lazy(() => import('@/pages/rate-plans/RatePlansPage').then(m => ({ default: m.RatePlansPage })))

export default function App() {
  return (
    <Suspense fallback={<PageSpinner />}>
      <Routes>
        {/* Auth routes */}
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<Login />} />
        </Route>

        {/* App routes */}
        <Route element={<AppLayout />}>
          <Route index                     element={<Dashboard />} />
          <Route path="customers"          element={<CustomersPage />} />
          <Route path="branches"           element={<BranchesPage />} />
          <Route path="chambers"           element={<ChambersPage />} />
          <Route path="storage-units"      element={<StorageUnitsPage />} />
          <Route path="inventory"          element={<InventoryPage />} />
          <Route path="invoices"           element={<InvoicesPage />} />
          <Route path="payments"           element={<PaymentsPage />} />
          <Route path="rate-plans"         element={<RatePlansPage />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
