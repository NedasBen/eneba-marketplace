import { createContext, useContext } from 'react'
import type { AuthResponse, User } from '../api/auth'

export type AuthStatus = 'loading' | 'authenticated' | 'guest'

export interface AuthContextValue {
  status: AuthStatus
  user: User | null
  /** Store the token from a login/register response and merge the guest cart. Never rejects. */
  signIn: (response: AuthResponse) => Promise<void>
  /** Revoke the token on the server (best effort) and forget it locally. */
  signOut: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

/** Session state from <AuthProvider> (mounted in AppLayout). */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (context === null) throw new Error('useAuth must be used inside <AuthProvider>.')
  return context
}
