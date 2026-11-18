import { describe, expect, it } from 'vitest'
import { loginPathFor, safeRedirectPath } from './redirect'

describe('safeRedirectPath', () => {
  it.each([
    ['/orders', '/orders'],
    ['/orders/01JD8X?tab=keys#top', '/orders/01JD8X?tab=keys#top'],
    ['/store?platform=pc,xbox&page=2', '/store?platform=pc,xbox&page=2'],
    ['/checkout/abc', '/checkout/abc'],
  ])('keeps the internal path %s', (raw, expected) => {
    expect(safeRedirectPath(raw)).toBe(expected)
  })

  it.each([
    ['absolute URL', 'https://evil.example/steal'],
    ['protocol-relative URL', '//evil.example'],
    ['backslash trick', '/\\evil.example'],
    ['leading backslashes', '\\\\evil.example'],
    ['tab inside the slashes', '/\t/evil.example'],
    ['newline inside the slashes', '/\n/evil.example'],
    ['encoded slashes', '/%2F%2Fevil.example'],
    ['encoded backslash', '/%5Cevil.example'],
    ['javascript URL', 'javascript:alert(document.cookie)'],
    ['data URL', 'data:text/html,<script>alert(1)</script>'],
    ['relative path', 'orders'],
    ['leading space', ' /orders'],
    ['the login page', '/login?redirect=/orders'],
    ['the register page', '/register/'],
    ['empty', ''],
    ['missing', null],
  ])('rejects %s', (_label, raw) => {
    expect(safeRedirectPath(raw)).toBe('/')
  })
})

describe('loginPathFor', () => {
  it('carries the current page as an encoded redirect', () => {
    expect(loginPathFor('/orders/01JD8X?tab=keys')).toBe('/login?redirect=%2Forders%2F01JD8X%3Ftab%3Dkeys')
  })

  it('omits the redirect for the home page and unsafe paths', () => {
    expect(loginPathFor('/')).toBe('/login')
    expect(loginPathFor('//evil.example')).toBe('/login')
  })
})
