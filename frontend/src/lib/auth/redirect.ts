const FALLBACK = '/'

/** Pages that must never be a post-login destination (they would loop). */
const AUTH_PAGES = ['/login', '/register']

/**
 * Turn an untrusted `?redirect=` value into a safe in-app path, or "/".
 *
 * Only same-origin paths are allowed, so a crafted link like
 * /login?redirect=https://evil.example can't bounce a freshly signed-in user
 * to another site (open redirect). Rejected: absolute and protocol-relative
 * URLs (`//evil`), backslash tricks (`/\evil`, which browsers treat as `//`),
 * control characters (browsers strip tabs/newlines, so `/\t/evil` becomes
 * `//evil`), `javascript:` and other schemes, and the auth pages themselves.
 */
export function safeRedirectPath(raw: string | null | undefined): string {
  if (typeof raw !== 'string' || raw === '') return FALLBACK

  // Control characters and backslashes have no place in our paths.
  // eslint-disable-next-line no-control-regex
  if (/[\u0000-\u001f\u007f\\]/.test(raw)) return FALLBACK

  if (!raw.startsWith('/') || raw.startsWith('//')) return FALLBACK

  let url: URL
  try {
    const base = 'https://app.invalid'
    url = new URL(raw, base)
    if (url.origin !== base) return FALLBACK
  } catch {
    return FALLBACK
  }

  // Encoded slashes ("/%2F%2Fevil") would decode into a protocol-relative URL somewhere down the line.
  if (/^\/(%2f|%5c)/i.test(url.pathname)) return FALLBACK

  if (AUTH_PAGES.includes(url.pathname.replace(/\/+$/, '') || '/')) return FALLBACK

  return `${url.pathname}${url.search}${url.hash}`
}

/** "/login?redirect=…" for the page the user was on. */
export function loginPathFor(currentPath: string): string {
  const target = safeRedirectPath(currentPath)
  return target === FALLBACK ? '/login' : `/login?redirect=${encodeURIComponent(target)}`
}
