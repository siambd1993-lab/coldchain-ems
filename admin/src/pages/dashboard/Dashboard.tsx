import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  Warehouse, Package, Database, Activity, Snowflake, Wind, Droplets, Gauge,
  Eye, BatteryCharging, Zap, AlertTriangle, CheckCircle2, Info, CalendarDays,
  Wifi, WifiOff, Sun,
} from 'lucide-react'
import { chambersApi }  from '@/api/chambers'
import { inventoryApi } from '@/api/inventory'
import { alertsApi, devicesApi, energyApi } from '@/api/iot'
import { auditApi } from '@/api/reports'
import { fetchWeather, weatherMeta } from '@/api/weather'
import { useAuthStore } from '@/stores/auth'
import { Card, CardHeader, CardTitle, Badge } from '@/components/ui'
import { cn } from '@/utils/cn'
import type { AlertRow, Chamber } from '@/types'

// ─── helpers ──────────────────────────────────────────────────────────────────

function useCan() {
  const user = useAuthStore((s) => s.user)
  return (perm: string): boolean =>
    !!user && (user.is_platform_admin === true || user.permissions?.includes(perm) === true)
}

function relTime(iso: string | null): string {
  if (!iso) return ''
  const mins = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60_000))
  if (mins < 60) return `${mins}m ago`
  if (mins < 1440) return `${Math.round(mins / 60)}h ago`
  return `${Math.round(mins / 1440)}d ago`
}

function inBand(chamber: Chamber): boolean | null {
  const t = chamber.current?.temp_c
  const { temp_min_c, temp_max_c } = chamber.target_band ?? {}
  if (t == null || temp_min_c == null || temp_max_c == null) return null
  return t >= temp_min_c && t <= temp_max_c
}

const ALERT_ICON: Record<AlertRow['severity'], { icon: typeof Info; tone: string; bg: string }> = {
  emergency: { icon: AlertTriangle, tone: 'text-white', bg: 'bg-purple-500' },
  critical:  { icon: AlertTriangle, tone: 'text-white', bg: 'bg-red-500' },
  warning:   { icon: AlertTriangle, tone: 'text-white', bg: 'bg-amber-400' },
  info:      { icon: Info,          tone: 'text-white', bg: 'bg-blue-400' },
}

// ─── page ─────────────────────────────────────────────────────────────────────

export function Dashboard() {
  const can = useCan()
  const user = useAuthStore((s) => s.user)

  const today = new Date().toISOString().slice(0, 10)

  const chambers = useQuery({
    queryKey: ['chambers-dash'],
    queryFn:  () => chambersApi.list({ per_page: 100 }),
    enabled:  can('chambers.view'),
  })
  const lots = useQuery({
    queryKey: ['lots-count'],
    queryFn:  () => inventoryApi.listLots({ per_page: 1 }),
    enabled:  can('stock.view'),
  })
  const devices = useQuery({
    queryKey: ['devices-dash'],
    queryFn:  () => devicesApi.list({ per_page: 100 }),
    enabled:  can('devices.view'),
  })
  const activity = useQuery({
    queryKey: ['activity-today'],
    queryFn:  () => auditApi.list({ per_page: 1, from: today, to: today }),
    enabled:  can('audit.view'),
  })
  const alerts = useQuery({
    queryKey: ['alerts-dash'],
    queryFn:  () => alertsApi.list({ per_page: 5 }),
    enabled:  can('alerts.view'),
    refetchInterval: 30_000,
  })
  const live = useQuery({
    queryKey: ['energy-live'],
    queryFn:  () => energyApi.live(),
    enabled:  can('energy.view'),
    refetchInterval: 10_000,
  })
  const energyToday = useQuery({
    queryKey: ['energy-today'],
    queryFn:  () => energyApi.summary({ from: today, to: today }),
    enabled:  can('energy.view'),
  })
  const weather = useQuery({
    queryKey: ['weather'],
    queryFn:  fetchWeather,
    staleTime: 15 * 60_000,
    retry: 1,
  })

  // Per-chamber humidity from sensor channels (falls back to chamber record).
  const humidityByChamber = new Map<number, number>()
  const sensorOnlineByChamber = new Map<number, boolean>()
  devices.data?.data.forEach((d) => {
    if (d.chamber_id != null) {
      sensorOnlineByChamber.set(d.chamber_id, sensorOnlineByChamber.get(d.chamber_id) || d.status === 'online')
      d.channels?.forEach((ch) => {
        if (ch.metric === 'humidity' && ch.last_value != null) humidityByChamber.set(d.chamber_id!, Math.round(ch.last_value))
      })
    }
  })

  const capacityMt = chambers.data
    ? chambers.data.data.reduce((sum, c) => sum + (c.capacity?.weight_kg ?? 0), 0) / 1000
    : null

  const battery = live.data?.battery
  const backupHours = battery && live.data && live.data.load_kw > 0
    ? ((battery.soc_pct / 100) * 30) / live.data.load_kw // assumes 30 kWh bank
    : null

  const WNow = weather.data ? weatherMeta(weather.data.now.code) : null

  return (
    <div className="space-y-5">
      {/* ── header strip ────────────────────────────────────────────────── */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Dashboard</h1>
          <p className="text-sm text-gray-500">Welcome back, {user?.name?.split(' ')[0] ?? 'operator'} — everything at a glance.</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {WNow && weather.data && (
            <span className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm">
              <WNow.icon className="h-4 w-4 text-amber-500" />
              <span className="font-semibold">{weather.data.now.temp_c}°C</span>
              <span className="text-gray-400">{WNow.label}</span>
            </span>
          )}
          <span className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm">
            <CalendarDays className="h-4 w-4 text-blue-500" />
            {new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
          </span>
          {battery && (
            <span className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm">
              <BatteryCharging className="h-4 w-4 text-green-500" />
              <span className="font-semibold">{battery.soc_pct}%</span>
              {backupHours != null && (
                <span className="text-gray-400">{Math.floor(backupHours)}h {Math.round((backupHours % 1) * 60)}m</span>
              )}
            </span>
          )}
        </div>
      </div>

      {/* ── gradient KPI cards ──────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <GradientStat
          to="/chambers"
          gradient="from-blue-500 to-blue-600"
          icon={Warehouse}
          label="Chambers"
          value={chambers.data ? String(chambers.data.meta.total) : '—'}
          sub="Total cold rooms"
        />
        <GradientStat
          to="/inventory"
          gradient="from-emerald-500 to-green-600"
          icon={Package}
          label="Inventory"
          value={lots.data ? lots.data.meta.total.toLocaleString() : '—'}
          sub="Stock lots"
        />
        <GradientStat
          to="/reports"
          gradient="from-violet-500 to-purple-600"
          icon={Database}
          label="Total Capacity"
          value={capacityMt != null ? `${capacityMt.toFixed(1)} MT` : '—'}
          sub="Max capacity"
        />
        <GradientStat
          to={can('audit.view') ? '/audit' : '/alerts'}
          gradient="from-amber-400 to-orange-500"
          icon={Activity}
          label="Today's Activity"
          value={
            activity.data?.meta?.total != null ? String(activity.data.meta.total)
              : alerts.data?.meta?.total != null ? String(alerts.data.meta.total) : '—'
          }
          sub={activity.data?.meta?.total != null ? 'Recorded actions' : 'Alerts'}
        />
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
        {/* ── left 2/3 ──────────────────────────────────────────────────── */}
        <div className="space-y-5 xl:col-span-2">
          {/* Chambers overview */}
          <Card>
            <CardHeader>
              <CardTitle><Snowflake className="mr-1.5 inline h-4 w-4 text-blue-500" />Chambers Overview</CardTitle>
              <Link to="/chambers" className="text-xs font-medium text-blue-600 hover:underline">Manage</Link>
            </CardHeader>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-gray-100 bg-gray-50/60 text-left text-xs uppercase tracking-wider text-gray-500">
                  <tr>
                    <th className="px-4 py-2.5 font-semibold">Chamber</th>
                    <th className="px-4 py-2.5 font-semibold">Temperature</th>
                    <th className="px-4 py-2.5 font-semibold">Humidity</th>
                    <th className="px-4 py-2.5 font-semibold">Capacity</th>
                    <th className="px-4 py-2.5 font-semibold">Status</th>
                    {can('devices.view') && <th className="px-4 py-2.5 font-semibold">Sensor</th>}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                  {chambers.data?.data.map((c) => {
                    const ok = inBand(c)
                    const humidity = humidityByChamber.get(c.id) ?? c.current?.humidity ?? null
                    const online = sensorOnlineByChamber.get(c.id)
                    return (
                      <tr key={c.id} className="hover:bg-gray-50/60">
                        <td className="px-4 py-2.5">
                          <span className="flex items-center gap-2 font-medium text-gray-900">
                            <Snowflake className="h-3.5 w-3.5 text-blue-400" /> {c.name}
                          </span>
                        </td>
                        <td className={cn('px-4 py-2.5 tabular-nums font-semibold', ok === false ? 'text-red-600' : 'text-gray-800')}>
                          {c.current?.temp_c != null ? `${c.current.temp_c.toFixed(1)}°C` : '—'}
                        </td>
                        <td className="px-4 py-2.5 tabular-nums text-gray-600">
                          {humidity != null ? `${humidity}%` : '—'}
                        </td>
                        <td className="px-4 py-2.5 tabular-nums text-gray-600">
                          {c.capacity?.weight_kg != null ? `${(c.capacity.weight_kg / 1000).toFixed(1)} MT` : '—'}
                        </td>
                        <td className="px-4 py-2.5">
                          {c.status !== 'operational'
                            ? <Badge variant="yellow">{c.status}</Badge>
                            : ok === false
                              ? <Badge variant="red">Excursion</Badge>
                              : <Badge variant="green">Normal</Badge>}
                        </td>
                        {can('devices.view') && (
                          <td className="px-4 py-2.5">
                            {online === undefined ? (
                              <span className="text-xs text-gray-300">none</span>
                            ) : online ? (
                              <span className="flex items-center gap-1 text-xs font-medium text-green-600"><Wifi className="h-3.5 w-3.5" /> online</span>
                            ) : (
                              <span className="flex items-center gap-1 text-xs font-medium text-red-500"><WifiOff className="h-3.5 w-3.5" /> offline</span>
                            )}
                          </td>
                        )}
                      </tr>
                    )
                  })}
                  {chambers.data?.data.length === 0 && (
                    <tr><td colSpan={6} className="py-10 text-center text-sm text-gray-400">No chambers yet.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </Card>

          {/* Power & battery row */}
          {can('energy.view') && (
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle><Zap className="mr-1.5 inline h-4 w-4 text-blue-500" />Power &amp; Energy</CardTitle>
                  <Link to="/energy" className="text-xs font-medium text-blue-600 hover:underline">Details</Link>
                </CardHeader>
                <div className="grid grid-cols-3 gap-3 px-4 pb-4">
                  <div>
                    <p className="text-[10px] uppercase tracking-wide text-gray-400">Load now</p>
                    <p className="text-xl font-bold text-gray-900">{live.data?.load_kw ?? '—'} <span className="text-xs font-normal">kW</span></p>
                  </div>
                  <div>
                    <p className="text-[10px] uppercase tracking-wide text-gray-400"><Sun className="mr-0.5 inline h-3 w-3 text-amber-400" />Solar now</p>
                    <p className="text-xl font-bold text-amber-500">{live.data?.solar_kw ?? '—'} <span className="text-xs font-normal text-gray-500">kW</span></p>
                  </div>
                  <div>
                    <p className="text-[10px] uppercase tracking-wide text-gray-400">Energy today</p>
                    <p className="text-xl font-bold text-gray-900">{energyToday.data ? energyToday.data.total_kwh.toLocaleString() : '—'} <span className="text-xs font-normal">kWh</span></p>
                  </div>
                </div>
                {live.data?.is_peak_hour && (
                  <p className="border-t border-amber-100 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-700">
                    ⚡ Peak tariff hours — battery is carrying the load
                  </p>
                )}
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle><BatteryCharging className="mr-1.5 inline h-4 w-4 text-green-500" />Battery / UPS</CardTitle>
                  <Badge variant="yellow">demo</Badge>
                </CardHeader>
                <div className="flex items-center gap-5 px-5 pb-4">
                  <SocRing pct={battery?.soc_pct ?? 0} />
                  <div className="space-y-1 text-sm">
                    <p className="text-gray-500">Backup time left{' '}
                      <span className="font-semibold text-gray-900">
                        {backupHours != null ? `${Math.floor(backupHours)}h ${Math.round((backupHours % 1) * 60)}m` : '—'}
                      </span>
                    </p>
                    <p className="text-gray-500">Status{' '}
                      <span className={cn('font-semibold', (battery?.kw ?? 0) > 0 ? 'text-emerald-600' : 'text-gray-900')}>
                        {battery ? (battery.kw > 0 ? 'Discharging' : battery.kw < 0 ? 'Charging' : 'Idle') : '—'}
                      </span>
                    </p>
                    <p className="text-gray-500">Flow{' '}
                      <span className="font-semibold text-gray-900">{battery ? `${Math.abs(battery.kw)} kW` : '—'}</span>
                    </p>
                  </div>
                </div>
              </Card>
            </div>
          )}
        </div>

        {/* ── right 1/3 ─────────────────────────────────────────────────── */}
        <div className="space-y-5">
          {/* Weather */}
          <Card>
            <CardHeader><CardTitle>Weather — Dhaka</CardTitle></CardHeader>
            {weather.isError && (
              <p className="px-4 pb-4 text-sm text-gray-400">Weather unavailable right now.</p>
            )}
            {weather.data && WNow && (
              <div className="px-4 pb-4">
                <div className="flex items-center gap-4">
                  <WNow.icon className="h-14 w-14 text-amber-400" />
                  <div>
                    <p className="text-3xl font-extrabold text-gray-900">{weather.data.now.temp_c}°C</p>
                    <p className="text-sm text-gray-500">{WNow.label}</p>
                  </div>
                </div>
                <div className="mt-4 grid grid-cols-2 gap-2 text-sm text-gray-600">
                  <span className="flex items-center gap-1.5"><Droplets className="h-3.5 w-3.5 text-blue-400" /> Humidity <strong className="ml-auto">{weather.data.now.humidity}%</strong></span>
                  <span className="flex items-center gap-1.5"><Wind className="h-3.5 w-3.5 text-gray-400" /> Wind <strong className="ml-auto">{weather.data.now.wind_kmh} km/h</strong></span>
                  <span className="flex items-center gap-1.5"><Gauge className="h-3.5 w-3.5 text-gray-400" /> Pressure <strong className="ml-auto">{weather.data.now.pressure} hPa</strong></span>
                  <span className="flex items-center gap-1.5"><Eye className="h-3.5 w-3.5 text-gray-400" /> Feels <strong className="ml-auto">{weather.data.now.temp_c}°C</strong></span>
                </div>
                <div className="mt-4 grid grid-cols-5 gap-1 border-t border-gray-100 pt-3 text-center">
                  {weather.data.daily.map((d) => {
                    const M = weatherMeta(d.code)
                    return (
                      <div key={d.date}>
                        <p className="text-[10px] font-semibold text-gray-400">
                          {new Date(d.date).toLocaleDateString('en', { weekday: 'short' })}
                        </p>
                        <M.icon className="mx-auto my-1 h-4 w-4 text-amber-400" />
                        <p className="text-[10px] text-gray-600"><strong>{d.max_c}°</strong> {d.min_c}°</p>
                      </div>
                    )
                  })}
                </div>
              </div>
            )}
          </Card>

          {/* Alerts feed */}
          {can('alerts.view') && (
            <Card>
              <CardHeader>
                <CardTitle>Recent Alerts</CardTitle>
                <Link to="/alerts" className="text-xs font-medium text-blue-600 hover:underline">View all</Link>
              </CardHeader>
              <ul className="space-y-1 px-3 pb-3">
                {alerts.data?.data.map((a) => {
                  const meta = a.status === 'resolved'
                    ? { icon: CheckCircle2, tone: 'text-white', bg: 'bg-emerald-500' }
                    : ALERT_ICON[a.severity]
                  const I = meta.icon
                  return (
                    <li key={a.id} className="flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-gray-50">
                      <span className={cn('mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full', meta.bg)}>
                        <I className={cn('h-3.5 w-3.5', meta.tone)} />
                      </span>
                      <span className="min-w-0">
                        <p className="truncate text-sm font-medium text-gray-800">{a.title}</p>
                        <p className="text-xs text-gray-400">{relTime(a.triggered_at)} · {a.status}</p>
                      </span>
                    </li>
                  )
                })}
                {alerts.data?.data.length === 0 && (
                  <li className="py-8 text-center text-sm text-gray-400">No alerts — all good ✅</li>
                )}
              </ul>
            </Card>
          )}
        </div>
      </div>
    </div>
  )
}

// ─── local components ─────────────────────────────────────────────────────────

function GradientStat({
  to, gradient, icon: Icon, label, value, sub,
}: {
  to: string; gradient: string; icon: typeof Warehouse
  label: string; value: string; sub: string
}) {
  return (
    <Link
      to={to}
      className={cn(
        'group relative overflow-hidden rounded-2xl bg-gradient-to-br p-5 text-white shadow-sm transition-shadow hover:shadow-md',
        gradient,
      )}
    >
      <div className="absolute -right-3 -top-3 flex h-20 w-20 items-center justify-center rounded-full bg-white/15">
        <Icon className="h-8 w-8 text-white/80" />
      </div>
      <p className="text-sm font-medium text-white/80">{label}</p>
      <p className="mt-2 text-3xl font-extrabold tracking-tight">{value}</p>
      <p className="mt-1 text-xs text-white/70">{sub}</p>
    </Link>
  )
}

function SocRing({ pct }: { pct: number }) {
  const r = 34
  const c = 2 * Math.PI * r
  return (
    <svg viewBox="0 0 90 90" className="h-24 w-24 shrink-0">
      <circle cx="45" cy="45" r={r} fill="none" stroke="#f3f4f6" strokeWidth="9" />
      <circle
        cx="45" cy="45" r={r} fill="none"
        stroke={pct > 50 ? '#22c55e' : pct > 25 ? '#f59e0b' : '#ef4444'}
        strokeWidth="9" strokeLinecap="round"
        strokeDasharray={`${(pct / 100) * c} ${c}`}
        transform="rotate(-90 45 45)"
      />
      <text x="45" y="49" textAnchor="middle" fontSize="17" fontWeight="800" fill="#111827">{pct}%</text>
    </svg>
  )
}
