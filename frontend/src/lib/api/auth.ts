import { apiRequest } from './client'

export interface User {
  id: number
  name: string
  email: string
}

export interface AuthResponse {
  user: User
  token: string
  expires_at: string
}

export const DEMO_ACCOUNT = { email: 'demo@example.com', password: 'password' } as const

export const authApi = {
  login: (email: string, password: string) => apiRequest<AuthResponse>('POST', '/auth/login', { body: { email, password } }),

  register: (input: { name: string; email: string; password: string; password_confirmation: string }) =>
    apiRequest<AuthResponse>('POST', '/auth/register', { body: input }),

  /** Silent: an expired stored token just means "signed out", no redirect. */
  me: () => apiRequest<{ user: User }>('GET', '/auth/me', { redirectOnUnauthorized: false }),

  logout: () => apiRequest<void>('POST', '/auth/logout', { redirectOnUnauthorized: false }),
}
