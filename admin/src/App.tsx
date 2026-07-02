import { Routes, Route } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import { AppLayout }  from '@/layouts/AppLayout'
import { AuthLayout } from '@/layouts/AuthLayout'
import { PageSpinner } from '@/components/ui'

const LandingPage    = lazy(() => import('@/pages/landing/LandingPage').then(m => ({ default: m.LandingPage })))
const Login          = lazy(() => import('@/pages/auth/Login').then(m => ({ default: m.Login })))
const Dashboard      = lazy(() => import('@/pages/dashboard/Dashboard').then(m => ({ default: m.Dashboard })))
const CustomersPage  = lazy(() => import('@/pages/customers/CustomersPage').then(m => ({ default: m.CustomersPage })))
const BranchesPage   = lazy(() => import('@/pages/branches/BranchesPage').then(m => ({ default: m.BranchesPage })))
const ChambersPage   = lazy(() => import('@/pages/chambers/ChambersPage').then(m => ({ default: m.ChambersPage })))
const StorageUnitsPage = lazy(() => import('@/pages/storage-units/StorageUnitsPage').then(m => ({ default: m.StorageUnitsPage })))
const ProductsPage   = lazy(() => import('@/pages/products/ProductsPage').then(m => ({ default: m.ProductsPage })))
const InventoryPage  = lazy(() => import('@/pages/inventory/InventoryPage').then(m => ({ default: m.InventoryPage })))
const InvoicesPage   = lazy(() => import('@/pages/billing/InvoicesPage').then(m => ({ default: m.InvoicesPage })))
const PaymentsPage   = lazy(() => import('@/pages/billing/PaymentsPage').then(m => ({ default: m.PaymentsPage })))
const RatePlansPage  = lazy(() => import('@/pages/rate-plans/RatePlansPage').then(m => ({ default: m.RatePlansPage })))
const UsersPage      = lazy(() => import('@/pages/team/UsersPage').then(m => ({ default: m.UsersPage })))
const RolesPage      = lazy(() => import('@/pages/team/RolesPage').then(m => ({ default: m.RolesPage })))
const ReportsPage    = lazy(() => import('@/pages/reports/ReportsPage').then(m => ({ default: m.ReportsPage })))
const AuditPage      = lazy(() => import('@/pages/reports/AuditPage').then(m => ({ default: m.AuditPage })))
const DevicesPage    = lazy(() => import('@/pages/iot/DevicesPage').then(m => ({ default: m.DevicesPage })))
const AlertsPage     = lazy(() => import('@/pages/iot/AlertsPage').then(m => ({ default: m.AlertsPage })))
const EnergyPage     = lazy(() => import('@/pages/iot/EnergyPage').then(m => ({ default: m.EnergyPage })))

export default function App() {
  return (
    <Suspense fallback={<PageSpinner />}>
      <Routes>
        {/* Public marketing page */}
        <Route path="/" element={<LandingPage />} />

        {/* Auth routes */}
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<Login />} />
        </Route>

        {/* App routes */}
        <Route element={<AppLayout />}>
          <Route path="dashboard"          element={<Dashboard />} />
          <Route path="customers"          element={<CustomersPage />} />
          <Route path="branches"           element={<BranchesPage />} />
          <Route path="chambers"           element={<ChambersPage />} />
          <Route path="storage-units"      element={<StorageUnitsPage />} />
          <Route path="inventory"          element={<InventoryPage />} />
          <Route path="products"           element={<ProductsPage />} />
          <Route path="invoices"           element={<InvoicesPage />} />
          <Route path="payments"           element={<PaymentsPage />} />
          <Route path="rate-plans"         element={<RatePlansPage />} />
          <Route path="reports"            element={<ReportsPage />} />
          <Route path="audit"              element={<AuditPage />} />
          <Route path="devices"            element={<DevicesPage />} />
          <Route path="alerts"             element={<AlertsPage />} />
          <Route path="energy"             element={<EnergyPage />} />
          <Route path="users"              element={<UsersPage />} />
          <Route path="roles"              element={<RolesPage />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
