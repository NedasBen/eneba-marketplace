/**
 * The Sanctum API token, kept in localStorage so it survives reloads (see
 * ARCHITECTURE.md "Authentication & token storage" for the XSS trade-off),
 * mirrored in memory so private mode / blocked storage still works for the tab.
 */
export const AUTH_TOKEN_KEY = 'auth_token'

let memoryToken: string | null = null

function storage(): Storage | null {
  try {
    return typeof window === 'undefined' ? null : window.localStorage
  } catch {
    return null
  }
}

export function getToken(): string | null {
  const store = storage()
  if (store === null) return memoryToken
  try {
    // Readable storage wins, so a logout in another tab signs this one out too.
    return store.getItem(AUTH_TOKEN_KEY)
  } catch {
    return memoryToken
  }
}

export function setToken(token: string): void {
  memoryToken = token
  try {
    storage()?.setItem(AUTH_TOKEN_KEY, token)
  } catch {
    // Storage blocked: the in-memory copy keeps this tab signed in.
  }
}

export function clearToken(): void {
  memoryToken = null
  try {
    storage()?.removeItem(AUTH_TOKEN_KEY)
  } catch {
    // Nothing to clear.
  }
}
