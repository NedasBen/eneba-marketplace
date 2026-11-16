import { useEffect, useState } from 'react'
import { apiGet, type PingResponse } from './lib/api'

type PingState =
  | { kind: 'loading' }
  | { kind: 'ok'; data: PingResponse }
  | { kind: 'error'; message: string }

function ApiStatus() {
  const [state, setState] = useState<PingState>({ kind: 'loading' })

  useEffect(() => {
    const controller = new AbortController()

    apiGet<PingResponse>('/ping', controller.signal)
      .then((data) => setState({ kind: 'ok', data }))
      .catch((error: unknown) => {
        if (controller.signal.aborted) {
          return
        }
        setState({ kind: 'error', message: error instanceof Error ? error.message : 'Unknown error' })
      })

    return () => controller.abort()
  }, [])

  if (state.kind === 'loading') {
    return (
      <p className="status status--loading">
        Contacting the API… the free-tier backend may take up to a minute to wake up.
      </p>
    )
  }

  if (state.kind === 'error') {
    return <p className="status status--error">API unreachable: {state.message}</p>
  }

  const { database, environment } = state.data

  return (
    <dl className="status status--ok">
      <div>
        <dt>API</dt>
        <dd>{state.data.status} ({environment})</dd>
      </div>
      <div>
        <dt>Database</dt>
        <dd>{database.connected ? `connected, ${database.latency_ms} ms` : 'disconnected'}</dd>
      </div>
      <div>
        <dt>DB time</dt>
        <dd>{database.server_time}</dd>
      </div>
    </dl>
  )
}

export default function App() {
  return (
    <>
      <header className="site-header">
        <span className="site-header__logo">eneba marketplace</span>
      </header>
      <main className="page">
        <h1>Deployment skeleton</h1>
        <p>React on Vercel → Laravel on Render → PostgreSQL on Neon.</p>
        <ApiStatus />
      </main>
    </>
  )
}
