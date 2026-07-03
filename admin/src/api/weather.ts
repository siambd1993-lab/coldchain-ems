import type { LucideIcon } from 'lucide-react'
import {
  Sun, CloudSun, Cloud, CloudFog, CloudDrizzle, CloudRain,
  CloudLightning, CloudSnow,
} from 'lucide-react'

/**
 * Weather via Open-Meteo — free, no API key, CORS-enabled. Uses a plain fetch
 * (NOT the authed apiClient: no Authorization header may leave our origin).
 * Coordinates default to Dhaka; facility-level geo can override later.
 */

export interface WeatherNow {
  temp_c:      number
  humidity:    number
  wind_kmh:    number
  pressure:    number
  code:        number
}

export interface WeatherDay {
  date:   string
  code:   number
  max_c:  number
  min_c:  number
}

export interface Weather {
  now:   WeatherNow
  daily: WeatherDay[]
}

const DHAKA = { lat: 23.8103, lon: 90.4125 }

export async function fetchWeather(): Promise<Weather> {
  const url =
    'https://api.open-meteo.com/v1/forecast' +
    `?latitude=${DHAKA.lat}&longitude=${DHAKA.lon}` +
    '&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,surface_pressure' +
    '&daily=weather_code,temperature_2m_max,temperature_2m_min' +
    '&timezone=Asia%2FDhaka&forecast_days=5'

  const resp = await fetch(url)
  if (!resp.ok) throw new Error(`weather HTTP ${resp.status}`)
  const j = await resp.json()

  return {
    now: {
      temp_c:   Math.round(j.current.temperature_2m),
      humidity: Math.round(j.current.relative_humidity_2m),
      wind_kmh: Math.round(j.current.wind_speed_10m),
      pressure: Math.round(j.current.surface_pressure),
      code:     j.current.weather_code,
    },
    daily: (j.daily.time as string[]).map((date, i) => ({
      date,
      code:  j.daily.weather_code[i],
      max_c: Math.round(j.daily.temperature_2m_max[i]),
      min_c: Math.round(j.daily.temperature_2m_min[i]),
    })),
  }
}

/** WMO weather code → icon + label. */
export function weatherMeta(code: number): { icon: LucideIcon; label: string } {
  if (code === 0) return { icon: Sun, label: 'Clear' }
  if (code <= 2) return { icon: CloudSun, label: 'Partly cloudy' }
  if (code === 3) return { icon: Cloud, label: 'Cloudy' }
  if (code <= 48) return { icon: CloudFog, label: 'Foggy' }
  if (code <= 57) return { icon: CloudDrizzle, label: 'Drizzle' }
  if (code <= 67) return { icon: CloudRain, label: 'Rain' }
  if (code <= 77) return { icon: CloudSnow, label: 'Snow' }
  if (code <= 82) return { icon: CloudRain, label: 'Showers' }
  return { icon: CloudLightning, label: 'Thunderstorm' }
}
