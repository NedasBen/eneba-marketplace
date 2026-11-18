import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { authApi, type AuthResponse, type User } from '../api/auth'
import { onUnauthorized } from '../api/client'
import { loginPathFor } from './redirect'
import { clearToken, getToken, setToken } from './token'
import { AuthContext, type AuthStatus } from './useAuth'

/**
 * Session state for the SPA. On start-up a stored token is checked with
 * GET /auth/me (silently: an expired token just means "guest"). Any later
 * 401 ends the session and sends the user to /login?redirect=<where they were>.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const [user, setUser] = useState<User | null>(null)
  const [status, setStatus] = useState<AuthStatus>(() => (getToken() ? 'loading' : 'guest'))

  useEffect(() => {
    if (status !== 'loading') return
    let cancelled = false

    authApi
      .me()
      .then(({ user: current }) => {
        if (cancelled) return
        setUser(current)
        setStatus('authenticated')
      })
      .catch(() => {
        if (cancelled) return
        // 401 already cleared the token; for network errors keep it and try again next load.
        setUser(null)
        setStatus('guest')
      })

    return () => {
      cancelled = true
    }
  }, [status])

  useEffect(() => {
    onUnauthorized(() => {
      setUser(null)
      setStatus('guest')
      const here = `${window.location.pathname}${window.location.search}`
      if (!window.location.pathname.startsWith('/login')) void navigate(loginPathFor(here), { replace: true })
    })
    return () => onUnauthorized(null)
  }, [navigate])

  const signIn = useCallback((response: AuthResponse) => {
    setToken(response.token)
    setUser(response.user)
    setStatus('authenticated')
  }, [])

  const signOut = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Already expired, offline, or the server is asleep: still sign out locally.
    } finally {
      clearToken()
      setUser(null)
      setStatus('guest')
    }
  }, [])

  const value = useMemo(() => ({ status, user, signIn, signOut }), [status, user, signIn, signOut])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
