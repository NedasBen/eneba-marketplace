import type { QueryClient } from '@tanstack/react-query'
import { useSyncExternalStore } from 'react'
import { CART_QUERY_KEY, cartApi, type MergeAdjustment } from './api/cart'
import { cart, type CartItem, type CartStore } from './cart'

/**
 * Turn the server's merge report into messages, naming products from the
 * guest cart (the server doesn't echo names for offers it couldn't find).
 */
export function describeMergeAdjustments(adjustments: MergeAdjustment[], guestItems: CartItem[]): string[] {
  const names = new Map(guestItems.map((item) => [item.offerId, item.snapshot.productName]))

  return adjustments.map((adjustment) => {
    const name = names.get(adjustment.offer_id) ?? 'An item from your guest cart'
    const keys = (count: number) => `${count} ${count === 1 ? 'key' : 'keys'}`

    switch (adjustment.kind) {
      case 'clamped':
        return `${name}: your cart now has ${keys(adjustment.quantity)}, the most available (you added ${adjustment.requested} as a guest).`
      case 'out_of_stock':
        return `${name} is out of stock and wasn't added.`
      case 'unavailable':
        return `${name} is no longer available and wasn't added.`
    }
  })
}

/** Messages from the last merge, shown once on the cart page. */
function createNoticeStore() {
  let messages: string[] = []
  const listeners = new Set<() => void>()
  const set = (next: string[]) => {
    messages = next
    listeners.forEach((listener) => listener())
  }

  return {
    get: () => messages,
    set,
    clear: () => set([]),
    subscribe(listener: () => void) {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
  }
}

export const mergeNotices = createNoticeStore()

export function useMergeNotices(): string[] {
  return useSyncExternalStore(mergeNotices.subscribe, mergeNotices.get, mergeNotices.get)
}

let inFlight: Promise<boolean> | null = null

/**
 * Fold the guest cart into the signed-in user's server cart, then clear it.
 *
 * Safe to call repeatedly (after login, on every start-up while signed in):
 * an empty guest cart is a no-op, concurrent calls share one request, and the
 * merge id makes a retried request a no-op on the server. On failure the
 * guest cart is kept for the next attempt. Resolves to whether it merged.
 */
export function mergeGuestCart(queryClient: QueryClient, store: CartStore = cart): Promise<boolean> {
  const items = store.getSnapshot()
  if (items.length === 0) return Promise.resolve(false)

  inFlight ??= cartApi
    .merge(
      store.mergeId(),
      items.map((item) => ({ offer_id: item.offerId, quantity: item.quantity })),
    )
    .then((result) => {
      queryClient.setQueryData(CART_QUERY_KEY, result.data)
      mergeNotices.set(describeMergeAdjustments(result.adjustments, items))
      store.clear()
      return true
    })
    .catch(() => false)
    .finally(() => {
      inFlight = null
    })

  return inFlight
}

/** After logout or an expired session: back to an empty guest cart. */
export function resetCartSession(queryClient: QueryClient, store: CartStore = cart): void {
  queryClient.removeQueries({ queryKey: CART_QUERY_KEY })
  mergeNotices.clear()
  store.clear()
}
