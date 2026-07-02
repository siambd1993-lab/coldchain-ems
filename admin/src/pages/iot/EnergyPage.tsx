import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Zap, Sun, Fuel, PlugZap, Leaf, Sparkles, TrendingDown, AlertTriangle, Info } from 'lucide-react'
import { energyApi } from '@/api/iot'
import { Card, CardHeader, CardTitle, Input, Button, Badge } from '@/components/ui'
import { formatMoney } from '@/utils/format'
import { cn } from '@/utils/cn'
import { EnergyFlow } from './EnergyFlow'

function iso(d: Date): string {
  return d.toISOString().slice(0, 10)
}

const SOURCE_META: Record<string, { label: string; color: string; icon: typeof Zap }> = {
  grid:      { label: 'Grid',      color: 'bg-blue-500',    icon: PlugZap },
  solar:     { label: 'Solar',     color: 'bg-amber-400',   icon: Sun },
  generator: { label: 'Generator', color: 'bg-red-400',     icon: Fuel },
  mixed:     { label: 'Mixed',     color: 'bg-gray-400',    icon: Zap },
}

export function EnergyPage() {
  const [from, setFrom] = useState(iso(new Date(Date.now() - 29 * 86_400_000)))
  const [to,   setTo]   = useState(iso(new Date()))
  const [range, setRange] = useState({ from, to })

  const { data, isError } = useQuery({
    queryKey: ['energy-summary', range],
    queryFn:  () => energyApi.summary(range),
  })

  const insights = useQuery({
    queryKey: ['energy-insights'],
    queryFn:  () => energyApi.insights(),
    staleTime: 60_000,
  })

  const maxDay = Math.max(
    1,
    ...(data?.series.map((s) => s.grid + s.solar + s.generator + s.mixed) ?? [1]),
  )

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Energy</h1>
          <p className="text-sm text-gray-500">
            Consumption, cost and solar share — live metering activates with the MQTT broker (VPS)
          </p>
        </div>
        <form
          className="flex items-center gap-2"
          onSubmit={(e) => { e.preventDefault(); setRange({ from, to }) }}
        >
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-36" />
          <span className="text-xs text-gray-400">to</span>
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-36" />
          <Button type="submit" size="sm" variant="outline">Apply</Button>
        </form>
      </div>

      {/* Live Tesla-style flow */}
      <EnergyFlow />

      {/* AI insights */}
      <Card>
        <CardHeader>
          <CardTitle>
            <Sparkles className="mr-1.5 inline h-4 w-4 text-purple-500" />
            AI energy insights
          </CardTitle>
          <span className="text-xs text-gray-400">rule engine v1 · learns more with live telemetry</span>
        </CardHeader>
        <div className="grid grid-cols-1 gap-3 px-4 pb-4 lg:grid-cols-2">
          {insights.data?.insights.map((ins, i) => {
            const Icon = ins.severity === 'warning' ? AlertTriangle
              : ins.severity === 'opportunity' ? TrendingDown : Info
            return (
              <div
                key={i}
                className={cn(
                  'rounded-xl border p-4',
                  ins.severity === 'warning' ? 'border-amber-200 bg-amber-50/40'
                    : ins.severity === 'opportunity' ? 'border-emerald-200 bg-emerald-50/40'
                    : 'border-gray-100 bg-gray-50/40',
                )}
              >
                <div className="flex items-start justify-between gap-2">
                  <p className="flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                    <Icon className={cn(
                      'h-4 w-4',
                      ins.severity === 'warning' ? 'text-amber-500'
                        : ins.severity === 'opportunity' ? 'text-emerald-500' : 'text-gray-400',
                    )} />
                    {ins.title}
                  </p>
                  {ins.saving_poisha_monthly !== undefined && (
                    <Badge variant="green">save ≈ {formatMoney(ins.saving_poisha_monthly)}/mo</Badge>
                  )}
                  {ins.cost_poisha_monthly !== undefined && (
                    <Badge variant="red">{formatMoney(ins.cost_poisha_monthly)}/mo</Badge>
                  )}
                </div>
                <p className="mt-1.5 text-sm leading-relaxed text-gray-600">{ins.detail}</p>
              </div>
            )
          })}
          {insights.data?.insights.length === 0 && (
            <p className="col-span-2 py-6 text-center text-sm text-gray-400">
              No recommendations right now — everything looks efficient.
            </p>
          )}
          {insights.isError && (
            <p className="col-span-2 py-6 text-center text-sm text-red-500">Could not load insights.</p>
          )}
        </div>
      </Card>

      {isError && (
        <Card><p className="py-8 text-center text-sm text-red-500">Could not load energy data.</p></Card>
      )}

      {data && (
        <>
          {/* KPI cards */}
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Card className="p-4">
              <p className="text-xs uppercase tracking-wide text-gray-400">Total consumption</p>
              <p className="mt-1 text-2xl font-bold text-gray-900">
                {data.total_kwh.toLocaleString()} <span className="text-sm font-normal">kWh</span>
              </p>
            </Card>
            <Card className="p-4">
              <p className="text-xs uppercase tracking-wide text-gray-400">Energy cost</p>
              <p className="mt-1 text-2xl font-bold text-gray-900">{formatMoney(data.total_cost_poisha)}</p>
            </Card>
            <Card className="p-4">
              <p className="text-xs uppercase tracking-wide text-gray-400">Solar share</p>
              <p className="mt-1 text-2xl font-bold text-amber-500">{data.solar_share_pct}%</p>
            </Card>
            <Card className="p-4">
              <p className="text-xs uppercase tracking-wide text-gray-400">
                <Leaf className="mr-1 inline h-3.5 w-3.5 text-emerald-500" />CO₂ emitted
              </p>
              <p className="mt-1 text-2xl font-bold text-gray-900">
                {data.total_co2_kg.toLocaleString()} <span className="text-sm font-normal">kg</span>
              </p>
            </Card>
          </div>

          {/* By source */}
          <Card>
            <CardHeader><CardTitle>By source</CardTitle></CardHeader>
            <div className="grid grid-cols-1 gap-4 px-4 pb-4 sm:grid-cols-3">
              {data.by_source.map((s) => {
                const meta = SOURCE_META[s.source] ?? SOURCE_META.mixed
                const Icon = meta.icon
                const pct  = data.total_kwh > 0 ? Math.round(s.kwh / data.total_kwh * 100) : 0
                return (
                  <div key={s.source} className="rounded-xl border border-gray-100 p-4">
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                        <Icon className="h-4 w-4 text-gray-400" /> {meta.label}
                      </span>
                      <span className="text-xs text-gray-400">{pct}%</span>
                    </div>
                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                      <div className={cn('h-full rounded-full', meta.color)} style={{ width: `${pct}%` }} />
                    </div>
                    <div className="mt-2 flex items-center justify-between text-sm">
                      <span className="text-gray-500">{s.kwh.toLocaleString()} kWh</span>
                      <span className="font-medium text-gray-800">{formatMoney(s.cost_poisha)}</span>
                    </div>
                  </div>
                )
              })}
              {data.by_source.length === 0 && (
                <p className="col-span-3 py-6 text-center text-sm text-gray-400">
                  No consumption recorded in this range.
                </p>
              )}
            </div>
          </Card>

          {/* Daily stacked bars */}
          <Card>
            <CardHeader><CardTitle>Daily consumption</CardTitle></CardHeader>
            <div className="px-4 pb-4">
              <div className="flex h-36 items-end gap-[2px]">
                {data.series.map((s) => {
                  const total = s.grid + s.solar + s.generator + s.mixed
                  return (
                    <div key={s.date} className="group relative flex flex-1 flex-col justify-end">
                      <div className="w-full rounded-t bg-red-400"   style={{ height: `${(s.generator / maxDay) * 128}px` }} />
                      <div className="w-full bg-amber-400"           style={{ height: `${(s.solar / maxDay) * 128}px` }} />
                      <div className="w-full bg-blue-500"            style={{ height: `${(s.grid / maxDay) * 128 + 1}px` }} />
                      <div className="pointer-events-none absolute bottom-full left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-[10px] text-white group-hover:block">
                        {s.date}: {total.toFixed(0)} kWh (grid {s.grid}, solar {s.solar}{s.generator ? `, gen ${s.generator}` : ''})
                      </div>
                    </div>
                  )
                })}
              </div>
              <p className="mt-1 text-center text-[10px] text-gray-400">
                {data.from} → {data.to} · blue = grid, amber = solar, red = generator
              </p>
            </div>
          </Card>
        </>
      )}
    </div>
  )
}
