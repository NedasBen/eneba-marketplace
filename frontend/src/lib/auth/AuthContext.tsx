import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { authApi, type AuthResponse, type User } from '../api/auth'
import { onUnauthorized } from '../api/client'
import { mergeGuestCart, resetCartSession } from '../cartSession'
import { loginPathFor } from './redirect'
import { clearToken, getToken, setToken } from './token'
import { AuthContext, type AuthStatus } from './useAuth'

/**
 * Session state for the SPA. On start-up a stored token is checked with
 * GET /auth/me (silently: an expired token just means "guest"). Any later
 * 401 ends the session and sends the user to /login?redirect=<where they were>.
 *
 * The cart follows the session: signing in merges the guest cart into the
 * server cart (once, see mergeGuestCart) and signing out leaves an empty
 * guest cart.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
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
        // Retry a merge that failed last time (e.g. the API was asleep at login).
        void mergeGuestCart(queryClient)
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
  }, [status, queryClient])

  useEffect(() => {
    onUnauthorized(() => {
      resetCartSession(queryClient)
      setUser(null)
      setStatus('guest')
      const here = `${window.location.pathname}${window.location.search}`
      if (!window.location.pathname.startsWith('/login')) void navigate(loginPathFor(here), { replace: true })
    })
    return () => onUnauthorized(null)
  }, [navigate, queryClient])

  const signIn = useCallback(
    async (response: AuthResponse) => {
      setToken(response.token)
      // Before the session counts as signed in, so the page we return to already shows the merged cart.
      await mergeGuestCart(queryClient)
      setUser(response.user)
      setStatus('authenticated')
    },
    [queryClient],
  )

  const signOut = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Already expired, offline, or the server is asleep: still sign out locally.
    } finally {
      clearToken()
      resetCartSession(queryClient)
      setUser(null)
      setStatus('guest')
    }
  }, [queryClient])

  const value = useMemo(() => ({ status, user, signIn, signOut }), [status, user, signIn, signOut])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
