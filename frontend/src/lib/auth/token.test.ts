import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AUTH_TOKEN_KEY, clearToken, getToken, setToken } from './token'

/** Tests run in Node: a Map-backed stand-in for window.localStorage. */
function fakeStorage() {
  const items = new Map<string, string>()
  return {
    getItem: (key: string) => items.get(key) ?? null,
    setItem: (key: string, value: string) => void items.set(key, value),
    removeItem: (key: string) => void items.delete(key),
  }
}

let storage: ReturnType<typeof fakeStorage>

beforeEach(() => {
  storage = fakeStorage()
  vi.stubGlobal('window', { localStorage: storage })
})

afterEach(() => {
  clearToken()
  vi.unstubAllGlobals()
})

describe('token store', () => {
  it('persists the token in localStorage and clears it', () => {
    setToken('1|abc')
    expect(storage.getItem(AUTH_TOKEN_KEY)).toBe('1|abc')
    expect(getToken()).toBe('1|abc')

    clearToken()
    expect(getToken()).toBeNull()
    expect(storage.getItem(AUTH_TOKEN_KEY)).toBeNull()
  })

  it('follows a logout in another tab', () => {
    setToken('1|abc')
    storage.removeItem(AUTH_TOKEN_KEY)
    expect(getToken()).toBeNull()
  })

  it('keeps the tab signed in when storage is blocked', () => {
    vi.stubGlobal('window', {
      get localStorage(): Storage {
        throw new DOMException('Blocked', 'SecurityError')
      },
    })

    setToken('1|abc')
    expect(getToken()).toBe('1|abc')
  })
})
