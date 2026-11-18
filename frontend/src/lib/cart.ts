import { useSyncExternalStore } from 'react'
import type { Money } from './api/types'

/**
 * Guest cart in localStorage. At sign-in it is sent once to POST /cart/merge
 * (with `mergeId()`, so a retry can't add it twice) and then cleared.
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
export const MERGE_ID_STORAGE_KEY = 'eneba.cart.v1.merge-id'
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
  /**
   * Idempotency key for merging this cart into the server cart. Stays the
   * same until the cart changes (so a retried merge is recognised), and is
   * replaced on any change (so new contents are merged as a new cart).
   */
  mergeId: () => string
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

const read = (storage: Storage | null, key: string): string | null => {
  try {
    return storage?.getItem(key) ?? null
  } catch {
    return null
  }
}

export function createCartStore(storage: Storage | null, now: () => Date = () => new Date(), newId: () => string = () => crypto.randomUUID()): CartStore {
  let items = parseCart(read(storage, CART_STORAGE_KEY))
  let memoryMergeId: string | null = null
  const listeners = new Set<() => void>()

  const commit = (next: CartItem[]) => {
    items = next
    memoryMergeId = null
    try {
      storage?.setItem(CART_STORAGE_KEY, JSON.stringify(next))
      storage?.removeItem(MERGE_ID_STORAGE_KEY)
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
      items = parseCart(read(storage, CART_STORAGE_KEY))
      memoryMergeId = null
      listeners.forEach((listener) => listener())
    },
    mergeId() {
      const stored = read(storage, MERGE_ID_STORAGE_KEY) ?? memoryMergeId
      if (stored !== null) return stored

      const id = newId()
      memoryMergeId = id
      try {
        storage?.setItem(MERGE_ID_STORAGE_KEY, id)
      } catch {
        // Kept in memory for this page view.
      }
      return id
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
