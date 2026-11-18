import { afterEach, describe, expect, it, vi } from 'vitest'
import { clearToken, getToken, setToken } from '../auth/token'
import { ApiError, apiRequest, onUnauthorized } from './client'

function respond(status: number, body: unknown = {}, headers: Record<string, string> = {}) {
  const fetchMock = vi.fn(async () => new Response(status === 204 ? null : JSON.stringify(body), { status, headers }))
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

/** The ApiError a request rejects with (fails the test if it resolves). */
async function rejection(request: Promise<unknown>): Promise<ApiError> {
  try {
    await request
  } catch (error) {
    if (error instanceof ApiError) return error
    throw error
  }
  throw new Error('Expected the request to fail.')
}

afterEach(() => {
  vi.unstubAllGlobals()
  clearToken()
  onUnauthorized(null)
})

describe('apiRequest', () => {
  it('sends the Bearer token when signed in, and none when signed out', async () => {
    const fetchMock = respond(200, { ok: true })

    await apiRequest('GET', '/auth/me')
    setToken('1|secret')
    await apiRequest('GET', '/auth/me')

    const headers = fetchMock.mock.calls.map((call) => (call as unknown as [string, RequestInit])[1].headers as Record<string, string>)
    expect(headers[0].Authorization).toBeUndefined()
    expect(headers[1].Authorization).toBe('Bearer 1|secret')
  })

  it('clears the token and ends the session on 401', async () => {
    respond(401, { message: 'Unauthenticated.' })
    const handler = vi.fn()
    onUnauthorized(handler)
    setToken('1|expired')

    await expect(apiRequest('GET', '/orders')).rejects.toMatchObject({ status: 401 })
    expect(getToken()).toBeNull()
    expect(handler).toHaveBeenCalledOnce()
  })

  it('clears the token without redirecting when asked (silent start-up check)', async () => {
    respond(401)
    const handler = vi.fn()
    onUnauthorized(handler)
    setToken('1|expired')

    await expect(apiRequest('GET', '/auth/me', { redirectOnUnauthorized: false })).rejects.toBeInstanceOf(ApiError)
    expect(getToken()).toBeNull()
    expect(handler).not.toHaveBeenCalled()
  })

  it('does not treat a 401 without a token (e.g. wrong login) as an expired session', async () => {
    respond(401)
    const handler = vi.fn()
    onUnauthorized(handler)

    await expect(apiRequest('POST', '/auth/login', { body: {} })).rejects.toBeInstanceOf(ApiError)
    expect(handler).not.toHaveBeenCalled()
  })

  it('exposes field errors and Retry-After', async () => {
    respond(422, { message: 'Invalid', errors: { email: ['These credentials do not match our records.'] } })
    const validation = await rejection(apiRequest('POST', '/auth/login', { body: {} }))
    expect(validation.fieldError('email')).toBe('These credentials do not match our records.')

    respond(429, { message: 'Too many login attempts.' }, { 'Retry-After': '42' })
    const throttled = await rejection(apiRequest('POST', '/auth/login', { body: {} }))
    expect(throttled.retryAfter).toBe(42)
  })

  it('returns undefined for 204 No Content', async () => {
    respond(204)
    await expect(apiRequest('POST', '/auth/logout')).resolves.toBeUndefined()
  })
})

describe('prefetched responses (public/prefetch.js)', () => {
  const homepageUrl = `${(import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '')}/api/v1/homepage`

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('reuses a prefetched GET once instead of fetching again', async () => {
    const fetchMock = respond(200, { fresh: true })
    vi.stubGlobal('window', { __apiPrefetch: { [homepageUrl]: Promise.resolve(new Response(JSON.stringify({ prefetched: true }), { status: 200 })) } })
    vi.stubGlobal('performance', { now: () => 500 })

    await expect(apiRequest('GET', '/homepage')).resolves.toEqual({ prefetched: true })
    await expect(apiRequest('GET', '/homepage')).resolves.toEqual({ fresh: true })
    expect(fetchMock).toHaveBeenCalledOnce()
  })

  it('ignores prefetches after the first seconds and failed prefetches', async () => {
    const fetchMock = respond(200, { fresh: true })
    vi.stubGlobal('window', { __apiPrefetch: { [homepageUrl]: Promise.resolve(null) } })
    vi.stubGlobal('performance', { now: () => 500 })
    await expect(apiRequest('GET', '/homepage')).resolves.toEqual({ fresh: true })

    vi.stubGlobal('window', { __apiPrefetch: { [homepageUrl]: Promise.resolve(new Response('{"old":true}')) } })
    vi.stubGlobal('performance', { now: () => 60_000 })
    await expect(apiRequest('GET', '/homepage')).resolves.toEqual({ fresh: true })
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})
