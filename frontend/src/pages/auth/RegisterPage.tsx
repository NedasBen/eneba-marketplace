import { useState, type FormEvent } from 'react'
import { Link, Navigate, useSearchParams } from 'react-router'
import { Button } from '../../components/ui/Button'
import { TextField } from '../../components/ui/TextField'
import { authApi } from '../../lib/api/auth'
import { ApiError } from '../../lib/api/client'
import { useAuth } from '../../lib/auth/useAuth'
import { PASSWORD_RULES } from '../../lib/auth/passwordRules'
import { safeRedirectPath } from '../../lib/auth/redirect'
import { cn } from '../../lib/cn'
import { AuthCard, FormAlert } from './AuthCard'

function PasswordChecklist({ password }: { password: string }) {
  return (
    <ul aria-label="Password requirements" className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
      {PASSWORD_RULES.map((rule) => {
        const met = rule.test(password)
        return (
          <li key={rule.label} className={cn('flex items-center gap-1 font-semibold', met ? 'text-success' : 'text-muted')}>
            <span aria-hidden>{met ? '✓' : '•'}</span>
            {rule.label}
            <span className="sr-only">{met ? '(met)' : '(not met)'}</span>
          </li>
        )
      })}
    </ul>
  )
}

export function RegisterPage() {
  const { status, signIn } = useAuth()
  const [searchParams] = useSearchParams()
  const redirectTo = safeRedirectPath(searchParams.get('redirect'))

  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)

  if (status === 'authenticated') return <Navigate to={redirectTo} replace />

  const update = (field: keyof typeof form) => (event: { target: { value: string } }) => setForm((current) => ({ ...current, [field]: event.target.value }))
  const confirmationMismatch = form.password_confirmation !== '' && form.password !== form.password_confirmation

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      signIn(await authApi.register(form))
    } catch (caught) {
      setError(caught instanceof ApiError ? caught : new ApiError(0, 'Something went wrong. Please try again.'))
    } finally {
      setSubmitting(false)
    }
  }

  const hasFieldErrors = error !== null && Object.keys(error.errors).length > 0
  const loginLink = redirectTo === '/' ? '/login' : `/login?redirect=${encodeURIComponent(redirectTo)}`

  return (
    <AuthCard
      title="Create an account"
      footer={
        <>
          Already have an account?{' '}
          <Link to={loginLink} className="font-semibold text-accent hover:text-accent-hover">
            Log in
          </Link>
        </>
      }
    >
      <form onSubmit={(event) => void onSubmit(event)} noValidate className="flex flex-col gap-4">
        {error && !hasFieldErrors && (
          <FormAlert>{error.isNetworkError ? 'Could not reach the server. It may be waking up, so try again in a moment.' : error.message}</FormAlert>
        )}

        <TextField label="Name" name="name" autoComplete="name" required maxLength={100} value={form.name} onChange={update('name')} error={error?.fieldError('name')} />
        <TextField label="Email" type="email" name="email" autoComplete="email" required value={form.email} onChange={update('email')} error={error?.fieldError('email')} />
        <TextField
          label="Password"
          type="password"
          name="password"
          autoComplete="new-password"
          required
          value={form.password}
          onChange={update('password')}
          error={error?.fieldError('password')}
          hint={<PasswordChecklist password={form.password} />}
        />
        <TextField
          label="Confirm password"
          type="password"
          name="password_confirmation"
          autoComplete="new-password"
          required
          value={form.password_confirmation}
          onChange={update('password_confirmation')}
          error={confirmationMismatch ? 'Passwords do not match.' : undefined}
        />

        <Button type="submit" fullWidth loading={submitting} disabled={confirmationMismatch}>
          Create account
        </Button>
      </form>
    </AuthCard>
  )
}
