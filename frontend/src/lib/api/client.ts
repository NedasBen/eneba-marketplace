import { clearToken, getToken } from '../auth/token'

const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '')

type QueryValue = string | number | boolean | string[] | null | undefined

/** A failed API call; `errors` holds Laravel's per-field messages on 422. */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>
  /** Seconds from a 429's Retry-After header. */
  readonly retryAfter: number | null
  /** The whole JSON error body, for endpoint-specific fields (e.g. checkout's `shortages`). */
  readonly body: Record<string, unknown>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}, retryAfter: number | null = null, body: Record<string, unknown> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.retryAfter = retryAfter
    this.body = body
  }

  /** 0 means the request never got a response (network down, API asleep, timed out). */
  get isNetworkError(): boolean {
    return this.status === 0
  }

  /** The request was abandoned after REQUEST_TIMEOUT_MS (a network error too). */
  get isTimeout(): boolean {
    return this.status === 0 && this.body.reason === 'timeout'
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

declare global {
  interface Window {
    /** Responses started by public/prefetch.js before the bundle ran, by full URL. */
    __apiPrefetch?: Record<string, Promise<Response | null>>
  }
}

/**
 * How long one request may take. Render's free tier can take up to a minute to
 * wake, so rather than leave a spinner hanging, an attempt gives up after 20 s;
 * queries retry network errors twice (main.tsx), which covers a cold start.
 */
export const REQUEST_TIMEOUT_MS = 20_000

/** Prefetched responses are only reused early on, so they can't be stale. */
const PREFETCH_MAX_AGE_MS = 10_000

/** A response public/prefetch.js already started for this URL (each is used once). */
function takePrefetched(url: string): Promise<Response | null> | undefined {
  if (typeof window === 'undefined' || window.__apiPrefetch === undefined) return undefined
  if (performance.now() > PREFETCH_MAX_AGE_MS) {
    window.__apiPrefetch = undefined
    return undefined
  }

  const pending = window.__apiPrefetch[url]
  delete window.__apiPrefetch[url]
  return pending
}

/** Resolves like `promise`, but rejects as soon as `signal` aborts. */
function untilAborted<T>(promise: Promise<T> | undefined, signal: AbortSignal): Promise<T | undefined> {
  if (promise === undefined) return Promise.resolve(undefined)
  if (signal.aborted) return Promise.reject(signal.reason)

  return new Promise((resolve, reject) => {
    const abort = () => reject(signal.reason)
    signal.addEventListener('abort', abort, { once: true })
    promise.then(resolve, reject).finally(() => signal.removeEventListener('abort', abort))
  })
}

interface RequestOptions {
  params?: Record<string, QueryValue>
  body?: unknown
  /** Extra request headers, e.g. `Idempotency-Key`. */
  headers?: Record<string, string>
  signal?: AbortSignal
  /** Overrides REQUEST_TIMEOUT_MS for this request. */
  timeoutMs?: number
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
  const headers: Record<string, string> = { ...options.headers, Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'

  const url = `${API_BASE_URL}/api/v1${path}${toQueryString(options.params)}`
  let response: Response

  const timeout = AbortSignal.timeout(options.timeoutMs ?? REQUEST_TIMEOUT_MS)
  const signal = options.signal ? AbortSignal.any([options.signal, timeout]) : timeout

  try {
    const prefetched = method === 'GET' ? await untilAborted(takePrefetched(url), signal) : null
    response =
      prefetched?.ok === true
        ? prefetched
        : await fetch(url, {
            method,
            headers,
            body: options.body === undefined ? undefined : JSON.stringify(options.body),
            signal,
          })
  } catch (error) {
    if (timeout.aborted && options.signal?.aborted !== true) {
      throw new ApiError(0, 'The server took too long to respond. Please try again.', {}, null, { reason: 'timeout' })
    }
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError(0, 'Could not reach the server. Please try again.')
  }

  if (response.status === 401 && token !== null) {
    clearToken()
    if (options.redirectOnUnauthorized !== false) unauthorizedHandler?.()
  }

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> } & Record<string, unknown>
    const retryAfter = Number(response.headers.get('Retry-After'))

    throw new ApiError(response.status, body.message ?? `Request failed (${response.status})`, body.errors, Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : null, body)
  }

  if (response.status === 204) return undefined as T

  return (await response.json()) as T
}

/** GET a JSON resource from the versioned API, e.g. `apiGet('/products', { q: 'witcher' })`. */
export function apiGet<T>(path: string, params?: Record<string, QueryValue>, signal?: AbortSignal): Promise<T> {
  return apiRequest<T>('GET', path, { params, signal })
}
