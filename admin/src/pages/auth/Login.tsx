import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm }     from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z }           from 'zod'
import { authApi }     from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { Button, Input, Alert } from '@/components/ui'
import type { ApiError } from '@/types'

const schema = z.object({
  email:    z.string().email('Enter a valid email'),
  password: z.string().min(1, 'Password is required'),
})
type FormValues = z.infer<typeof schema>

export function Login() {
  const navigate   = useNavigate()
  const setAuth = useAuthStore((s) => s.setAuth)

  const [apiError, setApiError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  async function onSubmit(values: FormValues) {
    setApiError(null)
    try {
      const data = await authApi.login(values)
      setAuth(data.user, data.access_token, data.refresh_token)
      navigate('/', { replace: true })
    } catch (err) {
      const e = err as { response?: { data?: ApiError } }
      setApiError(e?.response?.data?.message ?? 'Login failed. Check your credentials.')
    }
  }

  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
      <h2 className="mb-6 text-xl font-semibold text-gray-900">Sign in to your account</h2>

      {apiError && (
        <Alert variant="error" className="mb-5" onDismiss={() => setApiError(null)}>
          {apiError}
        </Alert>
      )}

      <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-4">
        <Input
          label="Email address"
          type="email"
          autoComplete="email"
          error={errors.email?.message}
          {...register('email')}
        />
        <Input
          label="Password"
          type="password"
          autoComplete="current-password"
          error={errors.password?.message}
          {...register('password')}
        />
        <Button type="submit" loading={isSubmitting} className="mt-2">
          Sign in
        </Button>
      </form>

      <p className="mt-5 text-center text-xs text-gray-400">
        Demo: <strong>admin@arcturus.test</strong> / <strong>password</strong>
      </p>
    </div>
  )
}
