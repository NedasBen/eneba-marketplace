import { Navigate, Outlet, useLocation } from 'react-router'
import { Container } from '../components/layout/Container'
import { useAuth } from '../lib/auth/useAuth'
import { loginPathFor } from '../lib/auth/redirect'

/**
 * Gate for checkout and order pages. Waits for the stored token to be
 * checked, then sends guests to /login?redirect=<this page>. The API stays
 * the real authority: any 401 later also ends the session.
 */
export function RequireAuth() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') {
    return (
      <Container className="py-9">
        <p role="status" className="text-soft">
          Checking your session…
        </p>
      </Container>
    )
  }

  if (status === 'guest') {
    return <Navigate to={loginPathFor(`${location.pathname}${location.search}`)} replace />
  }

  return <Outlet />
}
