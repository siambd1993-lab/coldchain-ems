import { Outlet, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { Snowflake } from 'lucide-react'

export function AuthLayout() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)

  if (isAuthenticated) return <Navigate to="/" replace />

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 via-white to-cyan-50">
      <div className="w-full max-w-md px-4">
        {/* Logo */}
        <div className="mb-8 flex flex-col items-center gap-3 text-center">
          <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 shadow-lg">
            <Snowflake className="h-8 w-8 text-white" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">ColdChain EMS</h1>
            <p className="text-sm text-gray-500">Cold Storage Management System</p>
          </div>
        </div>
        <Outlet />
      </div>
    </div>
  )
}
