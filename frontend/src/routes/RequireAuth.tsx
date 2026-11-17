import { Navigate, Outlet, useLocation } from 'react-router'

export const AUTH_TOKEN_KEY = 'auth_token'

function hasToken(): boolean {
  try {
    return window.localStorage.getItem(AUTH_TOKEN_KEY) !== null
  } catch {
    return false
  }
}

/**
 * Gate for checkout and order pages. Placeholder until auth lands: it only
 * checks that a token is stored; the API remains the real authority (401).
 */
export function RequireAuth() {
  const location = useLocation()

  if (!hasToken()) {
    const redirect = encodeURIComponent(location.pathname + location.search)
    return <Navigate to={`/login?redirect=${redirect}`} replace />
  }

  return <Outlet />
}
