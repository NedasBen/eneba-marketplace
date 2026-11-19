import { describe, expect, it } from 'vitest'
import vercelJson from '../vercel.json'

interface VercelConfig {
  headers: { source: string; headers: { key: string; value: string }[] }[]
}

const config: VercelConfig = vercelJson

function contentSecurityPolicy(): Map<string, string[]> {
  const header = config.headers.find((rule) => rule.source === '/(.*)')?.headers.find((entry) => entry.key === 'Content-Security-Policy')
  if (header === undefined) throw new Error('No Content-Security-Policy in vercel.json')

  return new Map(
    header.value
      .split(';')
      .map((directive) => directive.trim().split(/\s+/))
      .filter((parts) => parts[0] !== '')
      .map(([name, ...sources]) => [name, sources]),
  )
}

describe('Content-Security-Policy (vercel.json)', () => {
  const csp = contentSecurityPolicy()

  it('only runs our own scripts and loads our own fonts and styles', () => {
    expect(csp.get('script-src')).toEqual(["'self'"])
    expect(csp.get('font-src')).toEqual(["'self'"])
    expect(csp.get('style-src')?.filter((source) => source.startsWith('http'))).toEqual([])
  })

  it('allows only the API, Steam images and nothing that frames us', () => {
    expect(csp.get('connect-src')).toEqual(["'self'", 'https://*.onrender.com'])
    expect(csp.get('img-src')).toEqual(["'self'", 'data:', 'https://*.steamstatic.com'])
    expect(csp.get('frame-ancestors')).toEqual(["'none'"])
  })
})
