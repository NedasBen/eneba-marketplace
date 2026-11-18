import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useMemo } from 'react'
import { CART_QUERY_KEY, cartApi, type ServerCart } from './api/cart'
import { useOfferStates } from './api/catalog'
import { ApiError } from './api/client'
import { useAuth } from './auth/useAuth'
import { cart, cartItemCount, useCart, type CartItem } from './cart'
import { cartTotal, reconcileCart, serverCartLines, type CartLine } from './cartReconcile'

/**
 * One cart API for the UI. Guests use the localStorage cart (re-checked
 * against GET /offers); signed-in users use the server cart (GET /cart).
 * Both produce the same CartLine shape and the same notices.
 */

function useServerCart(enabled: boolean, refetchOnMount: boolean | 'always' = true) {
  return useQuery({
    queryKey: CART_QUERY_KEY,
    queryFn: ({ signal }) => cartApi.get(signal),
    enabled,
    staleTime: 15_000,
    refetchOnMount,
  })
}

/** Keys in the cart, for the header badge (no offer lookups for guests). */
export function useCartCount(): number {
  const { status } = useAuth()
  const guestItems = useCart()
  const server = useServerCart(status === 'authenticated')

  if (status === 'authenticated') return server.data?.item_count ?? 0
  if (status === 'loading') return 0
  return cartItemCount(guestItems)
}

export interface CartView {
  source: 'guest' | 'server'
  lines: CartLine[]
  /** Current-price total of purchasable lines; null while unknown. */
  total: ReturnType<typeof cartTotal>
  isLoading: boolean
  isFetching: boolean
  /** Couldn't load (server cart) or couldn't re-check prices (guest cart). */
  isError: boolean
}

/** Lines with live price and stock notices, for the cart page. */
export function useCartLines(): CartView {
  const { status } = useAuth()
  const guestItems = useCart()
  const isServer = status === 'authenticated'
  const server = useServerCart(isServer, 'always')
  const offers = useOfferStates(isServer ? [] : guestItems.map((item) => item.offerId))

  return useMemo(() => {
    if (status === 'loading') {
      return { source: 'guest', lines: [], total: null, isLoading: true, isFetching: true, isError: false }
    }

    if (isServer) {
      const lines = server.data ? serverCartLines(server.data) : []
      return { source: 'server', lines, total: cartTotal(lines), isLoading: server.isPending, isFetching: server.isFetching, isError: server.isError }
    }

    const lines = reconcileCart(guestItems, offers.data)
    return { source: 'guest', lines, total: cartTotal(lines), isLoading: false, isFetching: offers.isFetching, isError: offers.isError }
  }, [status, isServer, server.data, server.isPending, server.isFetching, server.isError, guestItems, offers.data, offers.isFetching, offers.isError])
}

export type AddOutcome = { status: 'added'; quantity: number } | { status: 'limit_reached'; message: string } | { status: 'error'; message: string }

export interface CartActions {
  /**
   * False while a stored token is being checked: we don't know yet whether
   * an add belongs in the guest cart or the server cart.
   */
  ready: boolean
  /** Add one key (guest: capped locally at 10 and `stock`; server: the API enforces the caps). */
  add: (item: Omit<CartItem, 'quantity' | 'addedAt'>, stock: number) => Promise<AddOutcome>
  /** Throws ApiError (e.g. 422 over stock) for the server cart. */
  setQuantity: (line: CartLine, quantity: number) => Promise<void>
  remove: (line: CartLine) => Promise<void>
}

export function useCartActions(): CartActions {
  const { status } = useAuth()
  const queryClient = useQueryClient()
  const isServer = status === 'authenticated'

  const store = useCallback((next: ServerCart) => queryClient.setQueryData(CART_QUERY_KEY, next), [queryClient])

  const add = useCallback<CartActions['add']>(
    async (item, stock) => {
      if (!isServer) {
        const result = cart.add(item, stock)
        return result.status === 'added' ? result : { status: 'limit_reached', message: 'Maximum for this offer is already in your cart' }
      }

      try {
        const next = await cartApi.add(item.offerId)
        store(next)
        return { status: 'added', quantity: next.items.find((line) => line.offer_id === item.offerId)?.quantity ?? 1 }
      } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
          return { status: 'limit_reached', message: error.fieldError('quantity') ?? error.fieldError('offer_id') ?? error.message }
        }
        return { status: 'error', message: error instanceof ApiError && error.isNetworkError ? 'Could not reach the server. Try again.' : 'Could not add to cart. Try again.' }
      }
    },
    [isServer, store],
  )

  const setQuantity = useCallback<CartActions['setQuantity']>(
    async (line, quantity) => {
      if (line.lineId === null) {
        cart.setQuantity(line.offerId, quantity)
        return
      }
      store(await cartApi.setQuantity(line.lineId, quantity))
    },
    [store],
  )

  const remove = useCallback<CartActions['remove']>(
    async (line) => {
      if (line.lineId === null) {
        cart.remove(line.offerId)
        return
      }
      store(await cartApi.remove(line.lineId))
    },
    [store],
  )

  const ready = status !== 'loading'

  return useMemo(() => ({ ready, add, setQuantity, remove }), [ready, add, setQuantity, remove])
}
