import { useQuery } from '@tanstack/react-query'
import { Sun, Home, Fuel, PlugZap, BatteryCharging } from 'lucide-react'
import { energyApi } from '@/api/iot'
import { Card, CardHeader, CardTitle, Badge } from '@/components/ui'
import { cn } from '@/utils/cn'

/**
 * Tesla-style live power flow. Nodes sit on a fixed SVG stage; each possible
 * flow is a curved path that lights up (animated dashes) when power moves
 * along it. Data refreshes every 10 s.
 */

// Node centers on the 640×360 stage.
const N = {
  solar:     { x: 320, y: 60  },
  grid:      { x: 80,  y: 180 },
  load:      { x: 560, y: 180 },
  battery:   { x: 200, y: 300 },
  generator: { x: 440, y: 300 },
}

// Path definitions per flow key "from-to".
const PATHS: Record<string, { d: string; color: string }> = {
  'solar-load':     { d: `M ${N.solar.x + 30} ${N.solar.y + 20} Q 480 90 ${N.load.x - 15} ${N.load.y - 35}`,      color: '#f59e0b' },
  'solar-battery':  { d: `M ${N.solar.x - 30} ${N.solar.y + 20} Q 200 120 ${N.battery.x + 5} ${N.battery.y - 40}`, color: '#f59e0b' },
  'solar-grid':     { d: `M ${N.solar.x - 35} ${N.solar.y + 10} Q 150 80 ${N.grid.x + 15} ${N.grid.y - 35}`,       color: '#10b981' },
  'battery-load':   { d: `M ${N.battery.x + 40} ${N.battery.y - 5} Q 400 280 ${N.load.x - 20} ${N.load.y + 35}`,   color: '#22c55e' },
  'grid-load':      { d: `M ${N.grid.x + 40} ${N.grid.y} Q 320 200 ${N.load.x - 45} ${N.load.y}`,                  color: '#3b82f6' },
  'generator-load': { d: `M ${N.generator.x + 35} ${N.generator.y - 15} Q 520 260 ${N.load.x - 15} ${N.load.y + 40}`, color: '#ef4444' },
}

function FlowNode({
  x, y, icon: Icon, label, value, sub, tone,
}: {
  x: number; y: number; icon: typeof Sun
  label: string; value: string; sub?: string
  tone: string
}) {
  return (
    <foreignObject x={x - 52} y={y - 44} width={104} height={96}>
      <div className="flex flex-col items-center gap-1 text-center">
        <div className={cn('flex h-14 w-14 items-center justify-center rounded-full border-2 bg-white shadow-sm', tone)}>
          <Icon className="h-6 w-6" />
        </div>
        <p className="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{label}</p>
        <p className="-mt-1 text-xs font-bold text-gray-800">{value}</p>
        {sub && <p className="-mt-1 text-[10px] text-gray-400">{sub}</p>}
      </div>
    </foreignObject>
  )
}

export function EnergyFlow() {
  const { data } = useQuery({
    queryKey:        ['energy-live'],
    queryFn:         () => energyApi.live(),
    refetchInterval: 10_000,
  })

  const active = new Map((data?.flows ?? []).map((f) => [`${f.from}-${f.to}`, f.kw]))
  const battery = data?.battery

  return (
    <Card>
      <CardHeader>
        <CardTitle>Live power flow</CardTitle>
        <span className="flex items-center gap-2">
          {data?.is_peak_hour && <Badge variant="red">peak tariff hours</Badge>}
          <Badge variant="yellow">demo simulation — goes live with MQTT</Badge>
        </span>
      </CardHeader>
      <div className="px-2 pb-3">
        <svg viewBox="0 0 640 360" className="mx-auto w-full max-w-3xl">
          <style>{`
            .flow-line { fill: none; stroke-width: 2.5; stroke-linecap: round; }
            .flow-idle { stroke: #e5e7eb; stroke-dasharray: 3 6; }
            .flow-on   { stroke-dasharray: 6 10; animation: flowdash 1.1s linear infinite; }
            @keyframes flowdash { to { stroke-dashoffset: -16; } }
          `}</style>

          {Object.entries(PATHS).map(([key, p]) => {
            const kw = active.get(key)
            return (
              <g key={key}>
                <path d={p.d} className={cn('flow-line', kw ? 'flow-on' : 'flow-idle')} style={kw ? { stroke: p.color } : undefined} />
                {kw !== undefined && (
                  <FlowLabel d={p.d} kw={kw} color={p.color} />
                )}
              </g>
            )
          })}

          <FlowNode
            x={N.solar.x} y={N.solar.y} icon={Sun} label="Solar"
            value={`${data?.solar_kw ?? 0} kW`}
            tone="border-amber-300 text-amber-500"
          />
          <FlowNode
            x={N.grid.x} y={N.grid.y} icon={PlugZap} label="Grid"
            value={`${Math.abs(data?.grid_kw ?? 0)} kW`}
            sub={(data?.grid_kw ?? 0) < 0 ? 'exporting' : 'importing'}
            tone="border-blue-300 text-blue-500"
          />
          <FlowNode
            x={N.load.x} y={N.load.y} icon={Home} label="Facility"
            value={`${data?.load_kw ?? 0} kW`}
            tone="border-gray-300 text-gray-600"
          />
          <FlowNode
            x={N.battery.x} y={N.battery.y} icon={BatteryCharging} label="Battery"
            value={`${battery?.soc_pct ?? 0}%`}
            sub={battery ? (battery.kw > 0 ? 'discharging' : battery.kw < 0 ? 'charging' : 'idle') : undefined}
            tone="border-green-300 text-green-600"
          />
          <FlowNode
            x={N.generator.x} y={N.generator.y} icon={Fuel} label="Generator"
            value={`${data?.generator_kw ?? 0} kW`}
            sub={(data?.generator_kw ?? 0) === 0 ? 'standby' : 'running'}
            tone="border-red-200 text-red-400"
          />
        </svg>
      </div>
    </Card>
  )
}

/** kW label at the midpoint of a quadratic path. */
function FlowLabel({ d, kw, color }: { d: string; kw: number; color: string }) {
  // Parse "M x0 y0 Q cx cy x1 y1" and evaluate the curve at t=0.5.
  const nums = d.match(/-?\d+(\.\d+)?/g)!.map(Number)
  const [x0, y0, cx, cy, x1, y1] = nums
  const mx = 0.25 * x0 + 0.5 * cx + 0.25 * x1
  const my = 0.25 * y0 + 0.5 * cy + 0.25 * y1
  return (
    <g>
      <rect x={mx - 21} y={my - 10} width={42} height={16} rx={8} fill="white" stroke={color} strokeWidth={1} />
      <text x={mx} y={my + 2} textAnchor="middle" fontSize={9.5} fontWeight={700} fill={color}>
        {kw.toFixed(1)} kW
      </text>
    </g>
  )
}
