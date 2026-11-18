import { useEffect, useState, type FormEvent } from 'react'
import { Link, Navigate, useSearchParams } from 'react-router'
import { Button } from '../../components/ui/Button'
import { TextField } from '../../components/ui/TextField'
import { authApi, DEMO_ACCOUNT } from '../../lib/api/auth'
import { ApiError } from '../../lib/api/client'
import { useAuth } from '../../lib/auth/useAuth'
import { safeRedirectPath } from '../../lib/auth/redirect'
import { AuthCard, FormAlert } from './AuthCard'

/** Seconds left until `until` (a timestamp), ticking once a second. */
function useSecondsLeft(until: number | null): number {
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (until === null) return
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [until])

  return until === null ? 0 : Math.max(0, Math.ceil((until - now) / 1000))
}

function loginErrorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return 'Something went wrong. Please try again.'
  if (error.isNetworkError) return 'Could not reach the server. It may be waking up, so try again in a moment.'
  // The API sends the same message for an unknown email and a wrong password.
  return error.fieldError('email') ?? error.fieldError('password') ?? error.message
}

export function LoginPage() {
  const { status, signIn } = useAuth()
  const [searchParams] = useSearchParams()
  const redirectTo = safeRedirectPath(searchParams.get('redirect'))

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [lockedUntil, setLockedUntil] = useState<number | null>(null)
  const secondsLocked = useSecondsLeft(lockedUntil)
  const locked = secondsLocked > 0

  // Signed in (just now or already): go where the user was headed.
  if (status === 'authenticated') return <Navigate to={redirectTo} replace />

  async function logIn(credentials: { email: string; password: string }) {
    setSubmitting(true)
    setError(null)

    try {
      signIn(await authApi.login(credentials.email, credentials.password))
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 429) {
        setLockedUntil(Date.now() + (caught.retryAfter ?? 60) * 1000)
        setError(null)
      } else {
        setError(loginErrorMessage(caught))
      }
    } finally {
      setSubmitting(false)
    }
  }

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    void logIn({ email, password })
  }

  function signInWithDemoAccount() {
    setEmail(DEMO_ACCOUNT.email)
    setPassword(DEMO_ACCOUNT.password)
    void logIn(DEMO_ACCOUNT)
  }

  const registerLink = redirectTo === '/' ? '/register' : `/register?redirect=${encodeURIComponent(redirectTo)}`

  return (
    <AuthCard
      title="Log in"
      footer={
        <>
          New here?{' '}
          <Link to={registerLink} className="font-semibold text-accent hover:text-accent-hover">
            Create an account
          </Link>
        </>
      }
    >
      {searchParams.has('redirect') && redirectTo !== '/' && <p className="text-sm text-soft">Log in to continue.</p>}

      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        {locked && <FormAlert>Too many login attempts. Try again in {secondsLocked} s.</FormAlert>}
        {error && !locked && <FormAlert>{error}</FormAlert>}

        <TextField label="Email" type="email" name="email" autoComplete="email" required value={email} onChange={(event) => setEmail(event.target.value)} />
        <TextField
          label="Password"
          type="password"
          name="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
        />

        <Button type="submit" fullWidth loading={submitting} disabled={locked || email === '' || password === ''}>
          Log in
        </Button>
      </form>

      <div className="flex flex-col gap-2">
        <Button variant="purple" fullWidth disabled={submitting || locked} onClick={signInWithDemoAccount}>
          Use demo account
        </Button>
        <p className="text-center text-xs text-muted">Signs in as {DEMO_ACCOUNT.email}, a seeded test account.</p>
      </div>
    </AuthCard>
  )
}
