import { describe, expect, it } from 'vitest'
import { CART_STORAGE_KEY, MAX_QUANTITY, cartItemCount, createCartStore, parseCart, type CartItem } from './cart'
import { cartTotal, reconcileCart } from './cartReconcile'
import type { OfferState } from './api/types'

class MemoryStorage implements Storage {
  private values = new Map<string, string>()
  get length() {
    return this.values.size
  }
  clear() {
    this.values.clear()
  }
  getItem(key: string) {
    return this.values.get(key) ?? null
  }
  key(index: number) {
    return [...this.values.keys()][index] ?? null
  }
  removeItem(key: string) {
    this.values.delete(key)
  }
  setItem(key: string, value: string) {
    this.values.set(key, value)
  }
}

const eur = (amount_cents: number) => ({ amount_cents, currency: 'EUR' })

const snapshot = (unitPrice = 1999): CartItem['snapshot'] => ({
  productSlug: 'elden-ring-steam-key-global',
  productName: 'ELDEN RING Steam Key GLOBAL',
  coverImageUrl: 'https://cdn.example/cover.jpg',
  sellerName: 'PixelVault',
  platformName: 'PC',
  regionName: 'GLOBAL',
  unitPrice: eur(unitPrice),
})

const offerState = (overrides: Partial<OfferState> = {}): OfferState => ({
  id: 1,
  is_active: true,
  price: eur(1999),
  stock: 5,
  seller: { name: 'PixelVault' },
  product: { slug: 'elden-ring-steam-key-global', name: 'ELDEN RING Steam Key GLOBAL' },
  ...overrides,
})

describe('cart store', () => {
  it('adds an item, then increments it, and persists to storage', () => {
    const storage = new MemoryStorage()
    const store = createCartStore(storage, () => new Date('2026-11-18T10:00:00Z'))

    expect(store.add({ offerId: 1, snapshot: snapshot() }, 5)).toEqual({ status: 'added', quantity: 1 })
    expect(store.add({ offerId: 1, snapshot: snapshot() }, 5)).toEqual({ status: 'added', quantity: 2 })

    expect(store.getSnapshot()).toHaveLength(1)
    expect(JSON.parse(storage.getItem(CART_STORAGE_KEY) ?? '[]')[0]).toMatchObject({ offerId: 1, quantity: 2, addedAt: '2026-11-18T10:00:00.000Z' })
  })

  it('never exceeds the offer stock or the maximum quantity', () => {
    const store = createCartStore(new MemoryStorage())

    store.add({ offerId: 1, snapshot: snapshot() }, 2)
    store.add({ offerId: 1, snapshot: snapshot() }, 2)
    expect(store.add({ offerId: 1, snapshot: snapshot() }, 2)).toEqual({ status: 'limit_reached', quantity: 2 })

    store.add({ offerId: 2, snapshot: snapshot() }, 99, 50)
    expect(store.getSnapshot().find((item) => item.offerId === 2)?.quantity).toBe(MAX_QUANTITY)
  })

  it('refuses to add an offer without stock', () => {
    const store = createCartStore(new MemoryStorage())

    expect(store.add({ offerId: 1, snapshot: snapshot() }, 0)).toEqual({ status: 'limit_reached', quantity: 0 })
    expect(store.getSnapshot()).toEqual([])
  })

  it('updates quantities within bounds, removes items and counts them', () => {
    const store = createCartStore(new MemoryStorage())
    store.add({ offerId: 1, snapshot: snapshot() }, 10)
    store.add({ offerId: 2, snapshot: snapshot() }, 10)

    store.setQuantity(1, 99)
    store.setQuantity(2, 0)
    expect(store.getSnapshot().map((item) => item.quantity)).toEqual([MAX_QUANTITY, 1])
    expect(cartItemCount(store.getSnapshot())).toBe(11)

    store.remove(1)
    expect(store.getSnapshot().map((item) => item.offerId)).toEqual([2])
  })

  it('notifies subscribers on every change', () => {
    const store = createCartStore(new MemoryStorage())
    let calls = 0
    const unsubscribe = store.subscribe(() => calls++)

    store.add({ offerId: 1, snapshot: snapshot() }, 5)
    store.remove(1)
    unsubscribe()
    store.add({ offerId: 1, snapshot: snapshot() }, 5)

    expect(calls).toBe(2)
  })

  it('picks up changes made by another tab without writing them back', () => {
    const shared = new MemoryStorage()
    const thisTab = createCartStore(shared)
    const otherTab = createCartStore(shared)
    otherTab.add({ offerId: 7, snapshot: snapshot() }, 5)

    let writes = 0
    const setItem = shared.setItem.bind(shared)
    shared.setItem = (key, value) => {
      writes++
      setItem(key, value)
    }

    thisTab.reload()

    expect(thisTab.getSnapshot().map((item) => item.offerId)).toEqual([7])
    expect(writes).toBe(0)
  })

  it('works without storage (private mode) and survives a failing storage', () => {
    const noStorage = createCartStore(null)
    expect(noStorage.add({ offerId: 1, snapshot: snapshot() }, 5).status).toBe('added')

    const full = new MemoryStorage()
    full.setItem = () => {
      throw new Error('QuotaExceededError')
    }
    expect(createCartStore(full).add({ offerId: 1, snapshot: snapshot() }, 5).status).toBe('added')
  })
})

describe('parseCart', () => {
  it('drops malformed, duplicate and tampered entries', () => {
    const good = { offerId: 1, quantity: 2, addedAt: 'x', snapshot: snapshot() }
    const raw = JSON.stringify([
      good,
      { ...good }, // duplicate offer
      { ...good, offerId: 2, quantity: 0 },
      { ...good, offerId: 3, quantity: 999 }, // capped
      { ...good, offerId: 4, snapshot: { ...snapshot(), unitPrice: '19.99' } },
      'nonsense',
    ])

    expect(parseCart(raw).map((item) => [item.offerId, item.quantity])).toEqual([
      [1, 2],
      [3, MAX_QUANTITY],
    ])
    expect(parseCart('{not json')).toEqual([])
    expect(parseCart(null)).toEqual([])
  })
})

describe('reconcileCart', () => {
  const item = (quantity = 1, unitPrice = 1999): CartItem => ({ offerId: 1, quantity, addedAt: 'x', snapshot: snapshot(unitPrice) })

  it('shows no notices and makes nothing purchasable before offers are loaded', () => {
    expect(reconcileCart([item()], undefined)).toEqual([{ item: item(), currentPrice: null, notices: [], purchasable: false }])
  })

  it('accepts a line whose offer is unchanged', () => {
    expect(reconcileCart([item()], [offerState()])[0]).toMatchObject({ notices: [], purchasable: true, currentPrice: eur(1999) })
  })

  it('flags a price change and uses the current price', () => {
    expect(reconcileCart([item(1, 1999)], [offerState({ price: eur(2299) })])[0]).toMatchObject({
      currentPrice: eur(2299),
      notices: [{ kind: 'price_changed', previous: eur(1999), current: eur(2299) }],
      purchasable: true,
    })
  })

  it('flags offers that are out of stock, paused or gone', () => {
    expect(reconcileCart([item()], [offerState({ stock: 0 })])[0]).toMatchObject({ notices: [{ kind: 'out_of_stock' }], purchasable: false })
    expect(reconcileCart([item()], [offerState({ is_active: false })])[0]).toMatchObject({ notices: [{ kind: 'unavailable' }], purchasable: false })
    expect(reconcileCart([item()], [])[0]).toMatchObject({ notices: [{ kind: 'unavailable' }], purchasable: false })
  })

  it('flags a quantity above the remaining stock', () => {
    expect(reconcileCart([item(4)], [offerState({ stock: 2 })])[0]).toMatchObject({
      notices: [{ kind: 'reduced_stock', available: 2 }],
      purchasable: false,
    })
  })

  it('totals only purchasable lines at current prices', () => {
    const lines = reconcileCart(
      [item(2, 1000), { ...item(1), offerId: 2 }],
      [offerState({ price: eur(1500) }), offerState({ id: 2, stock: 0 })],
    )

    expect(cartTotal(lines)).toEqual(eur(3000))
    expect(cartTotal(reconcileCart([item()], undefined))).toBeNull()
  })
})
