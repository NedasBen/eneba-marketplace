const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '')

type QueryValue = string | number | boolean | string[] | null | undefined

/** A failed API call; `errors` holds Laravel's per-field messages on 422. */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  /** 0 means the request never got a response (network down, API asleep). */
  get isNetworkError(): boolean {
    return this.status === 0
  }
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

/** GET a JSON resource from the versioned API, e.g. `apiGet('/products', { q: 'witcher' })`. */
export async function apiGet<T>(path: string, params?: Record<string, QueryValue>, signal?: AbortSignal): Promise<T> {
  let response: Response

  try {
    response = await fetch(`${API_BASE_URL}/api/v1${path}${toQueryString(params)}`, {
      headers: { Accept: 'application/json' },
      signal,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError(0, 'Could not reach the server. Please try again.')
  }

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> }
    throw new ApiError(response.status, body.message ?? `Request failed (${response.status})`, body.errors)
  }

  return (await response.json()) as T
}
