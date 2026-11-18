import { useSyncExternalStore } from 'react'
import type { Money } from './api/types'

/**
 * Guest cart in localStorage (merged into the server cart at sign-in later;
 * the item shape is what POST /cart/merge will accept).
 *
 * The snapshot is for DISPLAY ONLY. Prices are never trusted from here: the
 * cart page re-checks every offer (GET /offers) and the server recomputes
 * all prices from the current offers at checkout.
 */
export interface CartItem {
  offerId: number
  quantity: number
  addedAt: string
  snapshot: {
    productSlug: string
    productName: string
    coverImageUrl: string
    sellerName: string
    platformName: string
    regionName: string
    unitPrice: Money
  }
}

export const CART_STORAGE_KEY = 'eneba.cart.v1'
export const MAX_QUANTITY = 10

export type AddResult = { status: 'added'; quantity: number } | { status: 'limit_reached'; quantity: number }

export interface CartStore {
  getSnapshot: () => CartItem[]
  subscribe: (listener: () => void) => () => void
  /** Adds one (or `quantity`), capped at MAX_QUANTITY and the offer's stock. */
  add: (item: Omit<CartItem, 'quantity' | 'addedAt'>, stock: number, quantity?: number) => AddResult
  setQuantity: (offerId: number, quantity: number) => void
  remove: (offerId: number) => void
  clear: () => void
  /** Re-read storage without writing (another tab changed it). Writing here would ping-pong between tabs. */
  reload: () => void
}

const isMoney = (value: unknown): value is Money =>
  typeof value === 'object' && value !== null && Number.isInteger((value as Money).amount_cents) && typeof (value as Money).currency === 'string'

function isCartItem(value: unknown): value is CartItem {
  if (typeof value !== 'object' || value === null) return false
  const item = value as CartItem
  const snapshot = item.snapshot as Partial<CartItem['snapshot']> | undefined

  return (
    Number.isInteger(item.offerId) &&
    item.offerId > 0 &&
    Number.isInteger(item.quantity) &&
    item.quantity >= 1 &&
    typeof snapshot === 'object' &&
    snapshot !== null &&
    typeof snapshot.productSlug === 'string' &&
    typeof snapshot.productName === 'string' &&
    isMoney(snapshot.unitPrice)
  )
}

/** Parse stored items, silently dropping anything malformed or tampered with. */
export function parseCart(raw: string | null): CartItem[] {
  if (raw === null) return []

  try {
    const parsed: unknown = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []

    const seen = new Set<number>()
    return parsed.filter(isCartItem).flatMap((item) => {
      if (seen.has(item.offerId)) return []
      seen.add(item.offerId)
      return [{ ...item, quantity: Math.min(item.quantity, MAX_QUANTITY) }]
    })
  } catch {
    return []
  }
}

export function createCartStore(storage: Storage | null, now: () => Date = () => new Date()): CartStore {
  let items = parseCart(storage?.getItem(CART_STORAGE_KEY) ?? null)
  const listeners = new Set<() => void>()

  const commit = (next: CartItem[]) => {
    items = next
    try {
      storage?.setItem(CART_STORAGE_KEY, JSON.stringify(next))
    } catch {
      // Storage full or blocked: the cart still works for this page view.
    }
    listeners.forEach((listener) => listener())
  }

  return {
    getSnapshot: () => items,
    subscribe(listener) {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    add(item, stock, quantity = 1) {
      const existing = items.find((line) => line.offerId === item.offerId)
      const limit = Math.max(0, Math.min(MAX_QUANTITY, stock))
      const current = existing?.quantity ?? 0
      const next = Math.min(limit, current + quantity)

      if (next <= current) return { status: 'limit_reached', quantity: current }

      commit(
        existing
          ? items.map((line) => (line.offerId === item.offerId ? { ...line, quantity: next, snapshot: item.snapshot } : line))
          : [...items, { ...item, quantity: next, addedAt: now().toISOString() }],
      )

      return { status: 'added', quantity: next }
    },
    setQuantity(offerId, quantity) {
      const clamped = Math.max(1, Math.min(MAX_QUANTITY, Math.floor(quantity)))
      commit(items.map((line) => (line.offerId === offerId ? { ...line, quantity: clamped } : line)))
    },
    remove(offerId) {
      commit(items.filter((line) => line.offerId !== offerId))
    },
    clear() {
      commit([])
    },
    reload() {
      items = parseCart(storage?.getItem(CART_STORAGE_KEY) ?? null)
      listeners.forEach((listener) => listener())
    },
  }
}

function browserStorage(): Storage | null {
  try {
    return typeof window === 'undefined' ? null : window.localStorage
  } catch {
    return null
  }
}

export const cart = createCartStore(browserStorage())

// Keep tabs in sync: another tab changing the cart updates this one (read-only).
if (typeof window !== 'undefined') {
  window.addEventListener('storage', (event) => {
    if (event.key === CART_STORAGE_KEY) cart.reload()
  })
}

export function useCart(): CartItem[] {
  return useSyncExternalStore(cart.subscribe, cart.getSnapshot, cart.getSnapshot)
}

export function cartItemCount(items: CartItem[]): number {
  return items.reduce((total, item) => total + item.quantity, 0)
}
