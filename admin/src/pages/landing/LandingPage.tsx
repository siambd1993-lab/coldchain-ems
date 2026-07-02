import { Link } from 'react-router-dom'
import {
  Snowflake,
  Package,
  Receipt,
  Thermometer,
  Zap,
  Building2,
  ShieldCheck,
  ArrowRight,
  Warehouse,
  BellRing,
  BarChart3,
} from 'lucide-react'
import { useAuthStore } from '@/stores/auth'

const FEATURES = [
  {
    icon: Building2,
    title: 'Multi-Branch Facilities',
    text: 'Run several cold storage facilities from one dashboard — branches, chambers and storage units, each with its own capacity and team.',
  },
  {
    icon: Package,
    title: 'Lot-Level Inventory',
    text: 'Every intake gets a traceable lot code. Releases, transfers and adjustments are recorded in an append-only movement ledger.',
  },
  {
    icon: Receipt,
    title: 'Automated Billing (BDT)',
    text: 'Flexible rate plans — per kg, per pallet, daily or monthly. Invoices, payments and customer balances handled to the poisha.',
  },
  {
    icon: Thermometer,
    title: 'Cold-Chain Monitoring',
    text: 'IoT temperature and humidity sensors per chamber, with alert rules that catch an excursion before the stock is at risk.',
  },
  {
    icon: Zap,
    title: 'Energy Management',
    text: 'Track consumption per facility against tariffs, spot the expensive hours, and cut the largest cost in cold storage.',
  },
  {
    icon: ShieldCheck,
    title: 'Role-Based Security',
    text: 'Owners, managers, operators, accountants and auditors each see exactly what their role allows — with a full audit trail.',
  },
]

const STEPS = [
  {
    icon: Warehouse,
    title: 'Receive',
    text: 'Goods arrive, get weighed and stored — the system assigns the lot code and the chamber.',
  },
  {
    icon: BellRing,
    title: 'Monitor',
    text: 'Temperature, humidity and energy are watched around the clock with automatic alerts.',
  },
  {
    icon: BarChart3,
    title: 'Bill & Report',
    text: 'Storage charges accrue by your rate plan; invoices and reports are one click away.',
  },
]

export function LandingPage() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)

  return (
    <div className="min-h-screen bg-white text-gray-900">
      {/* ── Top nav ─────────────────────────────────────────────────────── */}
      <header className="sticky top-0 z-30 border-b border-gray-100 bg-white/90 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600">
              <Snowflake className="h-5 w-5 text-white" />
            </div>
            <span className="text-base font-bold">ColdChain EMS</span>
          </div>
          <Link
            to={isAuthenticated ? '/dashboard' : '/login'}
            className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700"
          >
            {isAuthenticated ? 'Open Dashboard' : 'Sign In'}
            <ArrowRight className="h-4 w-4" />
          </Link>
        </div>
      </header>

      {/* ── Hero ─────────────────────────────────────────────────────────── */}
      <section className="relative overflow-hidden">
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-blue-50 via-white to-white" />
        <div className="relative mx-auto max-w-6xl px-4 pb-20 pt-16 text-center sm:pt-24">
          <span className="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
            <Snowflake className="h-3.5 w-3.5" />
            Built for cold storage operators in Bangladesh
          </span>
          <h1 className="mx-auto mt-6 max-w-3xl text-4xl font-extrabold tracking-tight sm:text-5xl">
            Smart Cold Storage &{' '}
            <span className="text-blue-600">Energy Management</span>
          </h1>
          <p className="mx-auto mt-5 max-w-2xl text-lg text-gray-600">
            Inventory, billing, temperature monitoring and energy analytics for
            mini cold storage facilities — everything an operator needs, in one
            system that works on any device.
          </p>
          <div className="mt-8 flex items-center justify-center gap-3">
            <Link
              to={isAuthenticated ? '/dashboard' : '/login'}
              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
            >
              {isAuthenticated ? 'Open Dashboard' : 'Sign in to your facility'}
              <ArrowRight className="h-4 w-4" />
            </Link>
            <a
              href="#features"
              className="inline-flex items-center rounded-xl border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
            >
              See what it does
            </a>
          </div>
        </div>
      </section>

      {/* ── Features ─────────────────────────────────────────────────────── */}
      <section id="features" className="mx-auto max-w-6xl px-4 py-16">
        <h2 className="text-center text-2xl font-bold sm:text-3xl">
          Everything a cold storage runs on
        </h2>
        <p className="mx-auto mt-3 max-w-2xl text-center text-gray-600">
          From the weighbridge to the invoice — one connected workflow.
        </p>
        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map(({ icon: Icon, title, text }) => (
            <div
              key={title}
              className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-shadow hover:shadow-md"
            >
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50">
                <Icon className="h-5 w-5 text-blue-600" />
              </div>
              <h3 className="mt-4 text-base font-semibold">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-gray-600">{text}</p>
            </div>
          ))}
        </div>
      </section>

      {/* ── How it works ─────────────────────────────────────────────────── */}
      <section className="border-y border-gray-100 bg-gray-50/60">
        <div className="mx-auto max-w-6xl px-4 py-16">
          <h2 className="text-center text-2xl font-bold sm:text-3xl">How it works</h2>
          <div className="mt-10 grid gap-8 sm:grid-cols-3">
            {STEPS.map(({ icon: Icon, title, text }, i) => (
              <div key={title} className="text-center">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-white">
                  <Icon className="h-6 w-6" />
                </div>
                <div className="mt-3 text-xs font-semibold uppercase tracking-wider text-blue-600">
                  Step {i + 1}
                </div>
                <h3 className="mt-1 text-base font-semibold">{title}</h3>
                <p className="mx-auto mt-2 max-w-xs text-sm text-gray-600">{text}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA ──────────────────────────────────────────────────────────── */}
      <section className="mx-auto max-w-6xl px-4 py-16 text-center">
        <h2 className="text-2xl font-bold sm:text-3xl">
          Ready to see your facility in one screen?
        </h2>
        <p className="mx-auto mt-3 max-w-xl text-gray-600">
          Sign in with your operator account — or contact us to get your cold
          storage onboarded.
        </p>
        <Link
          to={isAuthenticated ? '/dashboard' : '/login'}
          className="mt-7 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-8 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
        >
          {isAuthenticated ? 'Open Dashboard' : 'Sign In'}
          <ArrowRight className="h-4 w-4" />
        </Link>
      </section>

      {/* ── Footer ───────────────────────────────────────────────────────── */}
      <footer className="border-t border-gray-100">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 sm:flex-row">
          <div className="flex items-center gap-2 text-sm text-gray-500">
            <div className="flex h-6 w-6 items-center justify-center rounded bg-blue-600">
              <Snowflake className="h-3.5 w-3.5 text-white" />
            </div>
            ColdChain EMS — Smart Mini Cold Storage & Energy Management
          </div>
          <div className="text-xs text-gray-400">
            © {new Date().getFullYear()} ColdChain EMS · Dhaka, Bangladesh
          </div>
        </div>
      </footer>
    </div>
  )
}
