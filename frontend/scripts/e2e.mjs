// End-to-end check of the main buyer journey in headless Chrome, driven over the
// Chrome DevTools Protocol. No dependencies: Node 22+ (built-in fetch and WebSocket)
// and a local Chrome or Edge.
//
//   npm run e2e                                    # local: vite on :5173, API on :8000
//   E2E_APP_URL=https://eneba-marketplace-coral.vercel.app \
//   E2E_API_URL=https://eneba-marketplace-api-b54c.onrender.com npm run e2e
//
// Env: E2E_APP_URL, E2E_API_URL, CHROME_PATH (auto-detected if unset),
//      E2E_SCREENSHOTS (directory; screenshots are only saved when set).
//
// Each run registers a fresh throwaway account (e2e-…@example.test) and buys one
// key with the simulated success card, so it never touches the shared demo account.

import { spawn } from 'node:child_process'
import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const app = (process.env.E2E_APP_URL ?? 'http://localhost:5173').replace(/\/+$/, '')
const api = (process.env.E2E_API_URL ?? 'http://localhost:8000').replace(/\/+$/, '')
const shotsDir = process.env.E2E_SCREENSHOTS
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

function findChrome() {
  if (process.env.CHROME_PATH) return process.env.CHROME_PATH
  const candidates = {
    win32: [
      `${process.env.PROGRAMFILES}\\Google\\Chrome\\Application\\chrome.exe`,
      `${process.env['PROGRAMFILES(X86)']}\\Google\\Chrome\\Application\\chrome.exe`,
      `${process.env.LOCALAPPDATA}\\Google\\Chrome\\Application\\chrome.exe`,
      `${process.env['PROGRAMFILES(X86)']}\\Microsoft\\Edge\\Application\\msedge.exe`,
    ],
    darwin: ['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge'],
    linux: ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser'],
  }[process.platform] ?? []
  const found = candidates.find((candidate) => existsSync(candidate))
  if (!found) throw new Error('Chrome not found: set CHROME_PATH')
  return found
}

// --- Results -----------------------------------------------------------------

const results = []
function check(name, ok, detail = '') {
  results.push({ name, ok })
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? `  (${detail})` : ''}`)
}

// --- Wake the API (Render's free tier sleeps) ---------------------------------

async function wakeApi() {
  const started = Date.now()
  while (Date.now() - started < 120_000) {
    try {
      const response = await fetch(`${api}/api/v1/ping`, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(30_000) })
      if (response.ok) return Date.now() - started
    } catch {}
    await sleep(2_000)
  }
  throw new Error(`API at ${api} did not respond within 2 minutes`)
}

// --- Chrome over CDP ------------------------------------------------------------

const profileDir = mkdtempSync(join(tmpdir(), 'eneba-e2e-'))
const port = 9400 + Math.floor(Math.random() * 400)
const chrome = spawn(findChrome(), ['--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', `--remote-debugging-port=${port}`, `--user-data-dir=${profileDir}`, '--window-size=1280,900', 'about:blank'], { stdio: 'ignore' })

async function pageSocketUrl() {
  for (let attempt = 0; attempt < 75; attempt++) {
    try {
      const page = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find((target) => target.type === 'page')
      if (page) return page.webSocketDebuggerUrl
    } catch {}
    await sleep(200)
  }
  throw new Error('Chrome did not start')
}

let socket
let nextId = 0
const pending = new Map()

async function connect() {
  socket = new WebSocket(await pageSocketUrl())
  await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true })
    socket.addEventListener('error', reject, { once: true })
  })
  socket.addEventListener('message', (event) => {
    const message = JSON.parse(event.data)
    if (message.id && pending.has(message.id)) {
      pending.get(message.id)(message)
      pending.delete(message.id)
    }
  })
}

const send = (method, params = {}) =>
  new Promise((resolve) => {
    const id = ++nextId
    pending.set(id, resolve)
    socket.send(JSON.stringify({ id, method, params }))
  })

async function evaluate(expression) {
  const { result } = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? 'evaluate failed')
  return result.result.value
}

async function waitFor(expression, label, timeout = 20_000) {
  const started = Date.now()
  while (Date.now() - started < timeout) {
    try {
      if (await evaluate(expression)) return
    } catch {}
    await sleep(150)
  }
  throw new Error(`Timed out waiting for: ${label}`)
}

async function goto(path) {
  await send('Page.navigate', { url: app + path })
  await waitFor('document.readyState === "complete" && !!document.querySelector("header")', `load ${path}`)
}

async function shot(name) {
  if (!shotsDir) return
  mkdirSync(shotsDir, { recursive: true })
  const { result } = await send('Page.captureScreenshot', { format: 'png' })
  writeFileSync(join(shotsDir, `${name}.png`), Buffer.from(result.data, 'base64'))
}

const js = (value) => JSON.stringify(value)
const path = () => evaluate('location.pathname + location.search')
const mainText = () => evaluate('document.querySelector("main").innerText')
const badge = () => evaluate('document.querySelector(\'header a[href="/cart"]\').getAttribute("aria-label")')

/** Click the first element matching `selector` whose text includes `text`. */
const click = (selector, text) =>
  evaluate(`(() => {
    const element = [...document.querySelectorAll(${js(selector)})].find((candidate) => candidate.textContent.includes(${js(text)}) && !candidate.disabled)
    if (!element) throw new Error('nothing to click: ' + ${js(`${selector} "${text}"`)})
    element.click()
    return true
  })()`)

/** Type into a React-controlled input (sets the value the way React listens for). */
const type = (selector, value) =>
  evaluate(`(() => {
    const input = document.querySelector(${js(selector)})
    if (!input) throw new Error('no input ' + ${js(selector)})
    input.focus()
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, ${js(value)})
    input.dispatchEvent(new Event('input', { bubbles: true }))
    return true
  })()`)

const resultCount = () => evaluate('Number((document.querySelector("section[aria-label=Results] strong")?.textContent ?? "").replace(/[^0-9]/g, "")) || null')

async function logOut() {
  await waitFor('!!document.querySelector("header button[aria-haspopup=menu]")', 'user menu button')
  await evaluate('document.querySelector("header button[aria-haspopup=menu]").click(), true')
  await waitFor('!!document.querySelector("[role=menu]")', 'user menu')
  await click('[role=menuitem]', 'Log out')
  await waitFor('document.querySelector("header").innerText.includes("Log in")', 'signed out')
}

// --- The journey ----------------------------------------------------------------

const account = {
  name: 'E2E Buyer',
  email: `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.test`,
  password: `e2e-pass-${Math.random().toString(36).slice(2, 10)}1`,
}

try {
  console.log(`App ${app}\nAPI ${api}\n`)
  const wokeIn = await wakeApi()
  console.log(`API responded after ${(wokeIn / 1000).toFixed(1)} s\n`)

  await connect()
  await send('Page.enable')
  await send('Runtime.enable')

  // 1. Homepage
  await goto('/')
  await waitFor('document.querySelectorAll(\'main a[href^="/product/"]\').length >= 4', 'homepage products')
  check('homepage shows products', true, `${await evaluate('document.querySelectorAll(\'main a[href^="/product/"]\').length')} product links`)
  check('homepage has a title', (await evaluate('document.title')).includes('Eneba'))
  await shot('01-home')

  // 2. Store with a filter
  await goto('/store')
  await waitFor('(() => { const strong = document.querySelector("section[aria-label=Results] strong"); return strong && /\\d/.test(strong.textContent) })()', 'store results')
  const allCount = await resultCount()
  const pcFilter = `[...document.querySelectorAll('label')].find((label) => label.querySelector('input[type=checkbox]') && label.innerText.trim().startsWith('PC'))`
  await waitFor(`!!${pcFilter}`, 'PC filter')
  await evaluate(`${pcFilter}.querySelector('input').click(), true`)
  await waitFor('location.search.includes("platform=pc")', 'platform filter in the URL')
  await waitFor(`(() => { const strong = document.querySelector("section[aria-label=Results] strong"); const n = Number((strong?.textContent ?? '').replace(/[^0-9]/g, '')); return n > 0 && n < ${allCount} && document.querySelector("section[aria-label=Results]").getAttribute("aria-busy") !== "true" })()`, 'filtered count')
  const pcCount = await resultCount()
  check('store PC filter narrows the results', pcCount > 0 && pcCount < allCount, `${allCount} → ${pcCount}`)
  await shot('02-store-pc')

  // 3. Search with a typo
  await type('header input[type=search]', 'wichter')
  await waitFor('[...document.querySelectorAll("[role=option]")].some((option) => /witcher/i.test(option.textContent))', 'suggestions for wichter')
  check('search suggestions correct "wichter" to Witcher', true)
  await evaluate('document.querySelector("header form[role=search]").requestSubmit(), true')
  await waitFor('location.pathname === "/store" && location.search.includes("q=wichter")', 'search results page')
  await waitFor('[...document.querySelectorAll(\'section[aria-label=Results] a[href^="/product/"]\')].some((link) => /witcher/i.test(link.textContent))', 'Witcher in results')
  check('search results for "wichter" include The Witcher', true, `${await resultCount()} results`)
  await shot('03-search')

  // 4. Product page, add to cart as a guest
  await evaluate('[...document.querySelectorAll(\'section[aria-label=Results] a[href^="/product/"]\')].find((link) => /witcher/i.test(link.textContent)).click(), true')
  await waitFor('location.pathname.startsWith("/product/") && !!document.querySelector("main h1")', 'product page')
  const productName = await evaluate('document.querySelector("main h1").innerText.trim()')
  check('product page shows the game', /witcher/i.test(productName), productName)
  await waitFor('[...document.querySelectorAll("main button")].some((button) => button.textContent.trim() === "Add to cart" && !button.disabled)', 'add to cart button')
  await click('main button', 'Add to cart')
  await waitFor(`document.querySelector('header a[href="/cart"]').getAttribute('aria-label') === 'Cart, 1 item'`, 'guest badge')
  check('guest add to cart updates the badge', true)
  await shot('04-product')

  // 5. Protected pages send guests to login and back
  await goto('/orders')
  await waitFor('location.pathname === "/login"', 'login redirect')
  check('guest on /orders is sent to login with a return path', (await path()) === '/login?redirect=%2Forders', await path())
  check('login page offers the demo account', await evaluate('[...document.querySelectorAll("button")].some((button) => button.textContent.includes("Use demo account"))'))

  // 6. Register a fresh account; the guest cart merges into it
  await goto('/register?redirect=%2Fcart')
  await type('input[name=name]', account.name)
  await type('input[name=email]', account.email)
  await type('input[name=password]', account.password)
  await type('input[name=password_confirmation]', account.password)
  await click('main button[type=submit]', 'Create account')
  await waitFor('location.pathname === "/cart"', 'cart after register')
  await waitFor(`document.querySelector("header").innerText.includes(${js(account.name)})`, 'name in header')
  check('register signs in and returns to the cart', true, account.email)
  await waitFor('document.querySelectorAll("main select").length === 1', 'merged cart line')
  check('guest cart merged into the new account', (await mainText()).includes(productName))
  check('guest cart cleared after the merge', (await evaluate('localStorage.getItem("eneba.cart.v1")')) === '[]')
  await shot('06-merged-cart')

  // 7. Log out and back in with the form: the server cart is still there
  await logOut()
  check('logout empties the header cart', (await badge()) === 'Cart', await badge())
  await goto('/login?redirect=%2Fcart')
  await type('input[name=email]', account.email)
  await type('input[name=password]', 'wrong-password-1')
  await click('main button[type=submit]', 'Log in')
  await waitFor('!!document.querySelector("main [role=alert]")', 'login error')
  check('wrong password shows an error', /do not match/i.test(await evaluate('document.querySelector("main [role=alert]").innerText')))
  await type('input[name=password]', account.password)
  await click('main button[type=submit]', 'Log in')
  await waitFor('location.pathname === "/cart"', 'cart after login')
  await waitFor('document.querySelectorAll("main select").length === 1', 'server cart line')
  check('login restores the server cart', (await badge()) === 'Cart, 1 item', await badge())

  // 8. Checkout: declined card, then the success card
  await waitFor('[...document.querySelectorAll("main button")].some((button) => button.textContent.trim() === "Checkout" && !button.disabled)', 'checkout button')
  await click('main button', 'Checkout')
  await waitFor('location.pathname.startsWith("/checkout/") && !!document.querySelector("[role=timer]")', 'checkout page')
  const reference = (await path()).split('/').pop()
  check('checkout reserves the keys with a countdown', /1[45]:\d\d/.test(await evaluate('document.querySelector("[role=timer]").innerText')), reference)
  await click('main button', '4000 0000 0000 0002')
  await click('main button', 'Pay now')
  await waitFor('!!document.querySelector("main [role=alert]")', 'decline message')
  const decline = await evaluate('document.querySelector("main [role=alert]").innerText')
  check('declined card shows a message and stays on checkout', decline.includes('declined') && (await path()) === `/checkout/${reference}`, decline.replace(/\n/g, ' '))
  await shot('08-declined')
  await click('main button', '4242 4242 4242 4242')
  await click('main button', 'Pay now')
  await waitFor(`location.pathname === "/orders/${reference}"`, 'order page after payment', 30_000)
  await waitFor('document.querySelectorAll(\'main input[aria-label="Product key"]\').length === 1', 'revealed key')
  check('successful payment reveals the key', (await mainText()).includes('Payment successful'))
  await waitFor(`document.querySelector('header a[href="/cart"]').getAttribute('aria-label') === 'Cart'`, 'cart emptied')
  check('bought line left the cart', true)
  await shot('08-paid')

  // 9. Order history
  await goto('/orders')
  await waitFor(`[...document.querySelectorAll('main a[href="/orders/${reference}"]')].length > 0`, 'order in history')
  const row = await evaluate(`document.querySelector('main a[href="/orders/${reference}"]').closest('li').innerText`)
  check('order history lists the order as paid', /paid/i.test(row) && row.includes(reference), row.replace(/\n/g, ' | '))
  await evaluate(`document.querySelector('main a[href="/orders/${reference}"]').click(), true`)
  await waitFor('document.querySelectorAll(\'main input[aria-label="Product key"]\').length === 1', 'key on the order page')
  check('order page shows the key again', true)
  await shot('09-orders')

  // 10. Unknown routes
  await goto('/this-page-does-not-exist')
  await waitFor('document.querySelector("main").innerText.includes("Page not found")', '404 page')
  check('unknown route shows the 404 page', true)

  await logOut()
} catch (error) {
  console.log(`ERROR ${error.message}`)
  check('journey completed', false)
  await shot('error').catch(() => {})
} finally {
  const failed = results.filter((result) => !result.ok).length
  console.log(`\n${results.length - failed}/${results.length} checks passed`)
  socket?.close()
  chrome.kill()
  await sleep(500)
  try {
    rmSync(profileDir, { recursive: true, force: true })
  } catch {}
  process.exit(failed ? 1 : 0)
}
