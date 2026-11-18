import { QueryClient } from '@tanstack/react-query'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { CART_QUERY_KEY, cartApi, type MergeAdjustment, type ServerCart } from './api/cart'
import { ApiError } from './api/client'
import { createCartStore } from './cart'
import { describeMergeAdjustments, mergeGuestCart, mergeNotices, resetCartSession } from './cartSession'

const eur = (amount_cents: number) => ({ amount_cents, currency: 'EUR' })
const emptyServerCart: ServerCart = { items: [], item_count: 3, total: eur(0) }

/** In-memory guest cart with one key of each offer and predictable merge ids. */
function guestStore(...offerIds: number[]) {
  let next = 0
  const store = createCartStore(
    null,
    () => new Date(),
    () => `merge-${++next}`,
  )
  for (const offerId of offerIds) {
    store.add(
      {
        offerId,
        snapshot: { productSlug: `p${offerId}`, productName: `Game ${offerId}`, coverImageUrl: '', sellerName: 'S', platformName: 'PC', regionName: 'GLOBAL', unitPrice: eur(1000) },
      },
      10,
    )
  }
  return store
}

afterEach(() => {
  vi.restoreAllMocks()
  mergeNotices.clear()
})

describe('describeMergeAdjustments', () => {
  it('names products from the guest cart', () => {
    const adjustments: MergeAdjustment[] = [
      { offer_id: 1, kind: 'clamped', requested: 6, quantity: 4 },
      { offer_id: 2, kind: 'out_of_stock', requested: 1, quantity: 0 },
      { offer_id: 3, kind: 'unavailable', requested: 1, quantity: 0 },
      { offer_id: 99, kind: 'unavailable', requested: 1, quantity: 0 },
    ]

    expect(describeMergeAdjustments(adjustments, guestStore(1, 2, 3).getSnapshot())).toEqual([
      'Game 1: your cart now has 4 keys, the most available (you added 6 as a guest).',
      "Game 2 is out of stock and wasn't added.",
      "Game 3 is no longer available and wasn't added.",
      "An item from your guest cart is no longer available and wasn't added.",
    ])
  })
})

describe('mergeGuestCart', () => {
  it('does nothing for an empty guest cart', async () => {
    const merge = vi.spyOn(cartApi, 'merge')

    await expect(mergeGuestCart(new QueryClient(), guestStore())).resolves.toBe(false)
    expect(merge).not.toHaveBeenCalled()
  })

  it('sends ids and quantities only, stores the server cart, keeps the notices and clears the guest cart', async () => {
    const merge = vi.spyOn(cartApi, 'merge').mockResolvedValue({ data: emptyServerCart, adjustments: [{ offer_id: 1, kind: 'out_of_stock', requested: 1, quantity: 0 }] })
    const queryClient = new QueryClient()
    const store = guestStore(1, 2)

    await expect(mergeGuestCart(queryClient, store)).resolves.toBe(true)

    expect(merge).toHaveBeenCalledWith('merge-1', [
      { offer_id: 1, quantity: 1 },
      { offer_id: 2, quantity: 1 },
    ])
    expect(queryClient.getQueryData(CART_QUERY_KEY)).toBe(emptyServerCart)
    expect(mergeNotices.get()).toEqual(["Game 1 is out of stock and wasn't added."])
    expect(store.getSnapshot()).toEqual([])
  })

  it('keeps the guest cart and its merge id when the merge fails, so the retry is the same merge', async () => {
    const merge = vi
      .spyOn(cartApi, 'merge')
      .mockRejectedValueOnce(new ApiError(0, 'offline'))
      .mockResolvedValueOnce({ data: emptyServerCart, adjustments: [] })
    const store = guestStore(1)

    await expect(mergeGuestCart(new QueryClient(), store)).resolves.toBe(false)
    expect(store.getSnapshot()).toHaveLength(1)

    await expect(mergeGuestCart(new QueryClient(), store)).resolves.toBe(true)
    expect(merge.mock.calls.map((call) => call[0])).toEqual(['merge-1', 'merge-1'])
  })

  it('shares one request between concurrent calls', async () => {
    const merge = vi.spyOn(cartApi, 'merge').mockResolvedValue({ data: emptyServerCart, adjustments: [] })
    const queryClient = new QueryClient()
    const store = guestStore(1)

    await Promise.all([mergeGuestCart(queryClient, store), mergeGuestCart(queryClient, store)])
    expect(merge).toHaveBeenCalledOnce()
  })
})

describe('resetCartSession', () => {
  it('drops the server cart and leaves an empty guest cart', () => {
    const queryClient = new QueryClient()
    queryClient.setQueryData(CART_QUERY_KEY, emptyServerCart)
    mergeNotices.set(['something'])
    const store = guestStore(1)

    resetCartSession(queryClient, store)

    expect(queryClient.getQueryData(CART_QUERY_KEY)).toBeUndefined()
    expect(mergeNotices.get()).toEqual([])
    expect(store.getSnapshot()).toEqual([])
  })
})
