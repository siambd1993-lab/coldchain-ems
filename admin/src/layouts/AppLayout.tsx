import { Outlet, Navigate, NavLink, useNavigate } from 'react-router-dom'
import { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Snowflake,
  LayoutDashboard,
  Users,
  Warehouse,
  Box,
  Boxes,
  Package,
  Receipt,
  CreditCard,
  Tag,
  ChevronLeft,
  ChevronRight,
  LogOut,
  Menu,
  Building2,
  ChevronDown,
  BarChart3,
  ScrollText,
  Cpu,
  BellRing,
  Zap,
  UserCog,
  ShieldCheck,
} from 'lucide-react'
import { useAuthStore } from '@/stores/auth'
import { useUiStore }   from '@/stores/ui'
import { authApi }      from '@/api/auth'
import { branchesApi }  from '@/api/branches'
import { Toaster }      from '@/components/ui/Toaster'
import { cn }           from '@/utils/cn'

interface NavItem {
  to:          string
  label:       string
  icon:        typeof LayoutDashboard
  end?:        boolean
  /** Hidden when the signed-in user lacks this permission (API enforces anyway). */
  permission?: string
}

interface NavSection {
  title: string | null
  items: NavItem[]
}

const NAV_SECTIONS: NavSection[] = [
  {
    title: null,
    items: [
      { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, end: true },
    ],
  },
  {
    title: 'Operations',
    items: [
      { to: '/customers',     label: 'Customers',     icon: Users,     permission: 'customers.view' },
      { to: '/inventory',     label: 'Inventory',     icon: Package,   permission: 'stock.view' },
      { to: '/products',      label: 'Products',      icon: Boxes,     permission: 'stock.view' },
      { to: '/branches',      label: 'Branches',      icon: Building2, permission: 'branches.view' },
      { to: '/chambers',      label: 'Chambers',      icon: Warehouse, permission: 'chambers.view' },
      { to: '/storage-units', label: 'Storage Units', icon: Box,       permission: 'storage_units.view' },
    ],
  },
  {
    title: 'Billing',
    items: [
      { to: '/invoices',   label: 'Invoices',   icon: Receipt,    permission: 'billing.view' },
      { to: '/payments',   label: 'Payments',   icon: CreditCard, permission: 'payments.view' },
      { to: '/rate-plans', label: 'Rate Plans', icon: Tag,        permission: 'billing.view' },
    ],
  },
  {
    title: 'Monitoring',
    items: [
      { to: '/devices', label: 'Devices', icon: Cpu,      permission: 'devices.view' },
      { to: '/alerts',  label: 'Alerts',  icon: BellRing, permission: 'alerts.view' },
      { to: '/energy',  label: 'Energy',  icon: Zap,      permission: 'energy.view' },
    ],
  },
  {
    title: 'Insight',
    items: [
      { to: '/reports', label: 'Reports',   icon: BarChart3,  permission: 'reports.view' },
      { to: '/audit',   label: 'Audit Log', icon: ScrollText, permission: 'audit.view' },
    ],
  },
  {
    title: 'Team',
    items: [
      { to: '/users', label: 'Users', icon: UserCog,     permission: 'users.view' },
      { to: '/roles', label: 'Roles', icon: ShieldCheck, permission: 'roles.view' },
    ],
  },
]

export function AppLayout() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  const user            = useAuthStore((s) => s.user)
  const logout          = useAuthStore((s) => s.logout)

  const sidebarOpen          = useUiStore((s) => s.sidebarOpen)
  const toggleSidebar        = useUiStore((s) => s.toggleSidebar)
  const activeBranchId       = useUiStore((s) => s.activeBranchId)
  const activeBranchName     = useUiStore((s) => s.activeBranchName)
  const availableBranches    = useUiStore((s) => s.availableBranches)
  const setActiveBranch      = useUiStore((s) => s.setActiveBranch)
  const setAvailableBranches = useUiStore((s) => s.setAvailableBranches)

  const [branchOpen, setBranchOpen] = useState(false)
  const navigate = useNavigate()

  // Fetch branches once authenticated and populate the branch switcher
  const { data: branchData } = useQuery({
    queryKey: ['branches-all'],
    queryFn:  () => branchesApi.list({ per_page: 100 }),
    enabled:  isAuthenticated,
    staleTime: 5 * 60 * 1000,
  })

  useEffect(() => {
    if (branchData?.data?.length) {
      setAvailableBranches(branchData.data)
      // Auto-select the user's home branch, or the first branch
      if (!activeBranchId) {
        const home = user?.home_branch_id
          ? branchData.data.find((b) => b.id === user.home_branch_id)
          : undefined
        setActiveBranch(home ?? branchData.data[0])
      }
    }
  }, [branchData, activeBranchId, user, setAvailableBranches, setActiveBranch])

  if (!isAuthenticated) return <Navigate to="/login" replace />

  async function handleLogout() {
    try { await authApi.logout() } catch { /* ignore */ }
    logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="flex h-screen overflow-hidden bg-gray-50">
      {/* ── Sidebar ─────────────────────────────────────────────────────── */}
      <aside
        className={cn(
          'flex flex-col border-r border-gray-200 bg-white transition-all duration-200',
          sidebarOpen ? 'w-56' : 'w-14',
        )}
      >
        {/* Logo */}
        <div className="flex h-14 items-center border-b border-gray-100 px-3">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600">
            <Snowflake className="h-5 w-5 text-white" />
          </div>
          {sidebarOpen && (
            <span className="ml-2 truncate text-sm font-semibold text-gray-900">
              ColdChain EMS
            </span>
          )}
        </div>

        {/* Nav */}
        <nav className="flex-1 overflow-y-auto p-2">
          {NAV_SECTIONS.map((section) => {
            // Platform admins see everything; tenant users see what their
            // permissions allow (the API enforces regardless).
            const items = section.items.filter(
              (item) => !item.permission
                || user?.is_platform_admin
                || user?.permissions?.includes(item.permission),
            )
            if (items.length === 0) return null

            return (
              <div key={section.title ?? 'root'} className="mb-1.5">
                {section.title && sidebarOpen && (
                  <p className="mb-0.5 mt-2 px-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                    {section.title}
                  </p>
                )}
                {items.map(({ to, label, icon: Icon, end }) => (
                  <NavLink
                    key={to}
                    to={to}
                    end={end}
                    className={({ isActive }) =>
                      cn(
                        'flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm font-medium transition-colors',
                        isActive
                          ? 'bg-blue-50 text-blue-700'
                          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
                      )
                    }
                  >
                    <Icon className="h-4 w-4 shrink-0" />
                    {sidebarOpen && <span className="truncate">{label}</span>}
                  </NavLink>
                ))}
              </div>
            )
          })}
        </nav>

        {/* Collapse toggle */}
        <div className="border-t border-gray-100 p-2">
          <button
            onClick={toggleSidebar}
            className="flex w-full items-center justify-center rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
          >
            {sidebarOpen ? (
              <ChevronLeft className="h-4 w-4" />
            ) : (
              <ChevronRight className="h-4 w-4" />
            )}
          </button>
        </div>
      </aside>

      {/* ── Main ────────────────────────────────────────────────────────── */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Header */}
        <header className="flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4">
          {/* Mobile sidebar toggle */}
          <button
            className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 lg:hidden"
            onClick={toggleSidebar}
          >
            <Menu className="h-5 w-5" />
          </button>

          <div className="flex items-center gap-3">
            {/* Branch switcher */}
            {availableBranches.length > 0 && (
              <div className="relative">
                <button
                  onClick={() => setBranchOpen((o) => !o)}
                  className="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100"
                >
                  <Building2 className="h-4 w-4 text-gray-400" />
                  <span>{activeBranchName ?? 'All branches'}</span>
                  <ChevronDown className="h-3 w-3 text-gray-400" />
                </button>
                {branchOpen && (
                  <div className="absolute right-0 top-full z-20 mt-1 w-44 rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    {availableBranches.map((b) => (
                      <button
                        key={b.id}
                        className="flex w-full items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        onClick={() => { setActiveBranch(b); setBranchOpen(false) }}
                      >
                        {b.name}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* User / logout */}
          <div className="flex items-center gap-3">
            <span className="hidden text-sm text-gray-600 sm:block">
              {user?.name ?? user?.email}
            </span>
            <button
              onClick={handleLogout}
              className="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-800"
            >
              <LogOut className="h-4 w-4" />
              <span className="hidden sm:block">Logout</span>
            </button>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>

      <Toaster />
    </div>
  )
}
