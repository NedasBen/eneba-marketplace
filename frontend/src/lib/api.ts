const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/+$/, '')

export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

/** GET a JSON resource from the versioned API, e.g. `apiGet('/ping')`. */
export async function apiGet<T>(path: string, signal?: AbortSignal): Promise<T> {
  const response = await fetch(`${API_BASE_URL}/api/v1${path}`, {
    headers: { Accept: 'application/json' },
    signal,
  })

  if (!response.ok) {
    throw new ApiError(response.status, `GET ${path} failed with ${response.status}`)
  }

  return (await response.json()) as T
}

export interface PingResponse {
  status: 'ok' | 'degraded'
  environment: string
  database: {
    connected: boolean
    server_time?: string
    latency_ms?: number
  }
}
