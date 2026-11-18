import { clearToken, getToken } from '../auth/token'

const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '')

type QueryValue = string | number | boolean | string[] | null | undefined

/** A failed API call; `errors` holds Laravel's per-field messages on 422. */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>
  /** Seconds from a 429's Retry-After header. */
  readonly retryAfter: number | null

  constructor(status: number, message: string, errors: Record<string, string[]> = {}, retryAfter: number | null = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.retryAfter = retryAfter
  }

  /** 0 means the request never got a response (network down, API asleep). */
  get isNetworkError(): boolean {
    return this.status === 0
  }

  /** First message for a field, e.g. `error.fieldError('email')`. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

/**
 * Called when the API rejects our token (401): the session is over. The auth
 * layer registers a handler that resets state and sends the user to /login.
 */
let unauthorizedHandler: (() => void) | null = null

export function onUnauthorized(handler: (() => void) | null): void {
  unauthorizedHandler = handler
}

/**
 * Serializes params the way the API expects: arrays as comma lists
 * (`platform=pc,xbox`), booleans as 1/0, empty values dropped.
 */
export function toQueryString(params: Record<string, QueryValue> = {}): string {
  const search = new URLSearchParams()

  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    if (Array.isArray(value)) {
      if (value.length > 0) search.set(key, value.join(','))
    } else if (typeof value === 'boolean') {
      search.set(key, value ? '1' : '0')
    } else {
      search.set(key, String(value))
    }
  }

  const query = search.toString()
  return query === '' ? '' : `?${query}`
}

interface RequestOptions {
  params?: Record<string, QueryValue>
  body?: unknown
  signal?: AbortSignal
  /**
   * Whether a 401 should end the session with a redirect to /login. Off for
   * the silent start-up check of a stored token and for logout itself.
   */
  redirectOnUnauthorized?: boolean
}

/**
 * Any call to the versioned API. Sends the Bearer token when there is one;
 * a 401 with a token clears it and triggers the unauthorized handler.
 */
export async function apiRequest<T>(method: 'GET' | 'POST' | 'PATCH' | 'DELETE', path: string, options: RequestOptions = {}): Promise<T> {
  const token = getToken()
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'

  let response: Response

  try {
    response = await fetch(`${API_BASE_URL}/api/v1${path}${toQueryString(options.params)}`, {
      method,
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: options.signal,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError(0, 'Could not reach the server. Please try again.')
  }

  if (response.status === 401 && token !== null) {
    clearToken()
    if (options.redirectOnUnauthorized !== false) unauthorizedHandler?.()
  }

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> }
    const retryAfter = Number(response.headers.get('Retry-After'))

    throw new ApiError(response.status, body.message ?? `Request failed (${response.status})`, body.errors, Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : null)
  }

  if (response.status === 204) return undefined as T

  return (await response.json()) as T
}

/** GET a JSON resource from the versioned API, e.g. `apiGet('/products', { q: 'witcher' })`. */
export function apiGet<T>(path: string, params?: Record<string, QueryValue>, signal?: AbortSignal): Promise<T> {
  return apiRequest<T>('GET', path, { params, signal })
}
