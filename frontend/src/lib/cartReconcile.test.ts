import { describe, expect, it } from 'vitest'
import type { ServerCart, ServerCartLine } from './api/cart'
import type { OfferState } from './api/types'
import type { CartItem } from './cart'
import { cartTotal, reconcileCart, serverCartLines } from './cartReconcile'

const eur = (amount_cents: number) => ({ amount_cents, currency: 'EUR' })

const item = (quantity = 1, unitPrice = 1999, offerId = 1): CartItem => ({
  offerId,
  quantity,
  addedAt: 'x',
  snapshot: {
    productSlug: 'elden-ring-steam-key-global',
    productName: 'ELDEN RING Steam Key GLOBAL',
    coverImageUrl: 'https://cdn.example/cover.jpg',
    sellerName: 'PixelVault',
    platformName: 'PC',
    regionName: 'GLOBAL',
    unitPrice: eur(unitPrice),
  },
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

const serverLine = (overrides: Partial<ServerCartLine> = {}): ServerCartLine => ({
  id: 10,
  offer_id: 1,
  quantity: 1,
  is_active: true,
  stock: 5,
  max_quantity: 5,
  unit_price: eur(1999),
  price_seen: eur(1999),
  line_total: eur(1999),
  seller: { name: 'PixelVault' },
  product: {
    slug: 'elden-ring-steam-key-global',
    name: 'ELDEN RING Steam Key GLOBAL',
    cover_image_url: 'https://cdn.example/cover.jpg',
    platform_name: 'PC',
    region_name: 'GLOBAL',
  },
  ...overrides,
})

const serverCart = (...items: ServerCartLine[]): ServerCart => ({ items, item_count: 0, total: eur(0) })

describe('reconcileCart (guest)', () => {
  it('shows no notices and makes nothing purchasable before offers are loaded', () => {
    expect(reconcileCart([item()], undefined)[0]).toMatchObject({ lineId: null, currentPrice: null, notices: [], purchasable: false, maxQuantity: null })
  })

  it('accepts a line whose offer is unchanged', () => {
    expect(reconcileCart([item()], [offerState()])[0]).toMatchObject({ notices: [], purchasable: true, currentPrice: eur(1999), maxQuantity: 5 })
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
      maxQuantity: 2,
    })
  })

  it('totals only purchasable lines at current prices', () => {
    const lines = reconcileCart([item(2, 1000), item(1, 1999, 2)], [offerState({ price: eur(1500) }), offerState({ id: 2, stock: 0 })])

    expect(cartTotal(lines)).toEqual(eur(3000))
    expect(cartTotal(reconcileCart([item()], undefined))).toBeNull()
  })
})

describe('serverCartLines', () => {
  it('maps a line to the same shape as a guest line', () => {
    const [server] = serverCartLines(serverCart(serverLine()))
    const [guest] = reconcileCart([item()], [offerState()])

    expect(server).toEqual({ ...guest, lineId: 10 })
  })

  it('gives the same notices as the guest cart for the same situation', () => {
    const cases: [Partial<ServerCartLine>, CartItem, OfferState][] = [
      [{ price_seen: eur(1999), unit_price: eur(2299) }, item(1, 1999), offerState({ price: eur(2299) })],
      [{ stock: 0 }, item(), offerState({ stock: 0 })],
      [{ is_active: false }, item(), offerState({ is_active: false })],
      [{ quantity: 4, stock: 2 }, item(4), offerState({ stock: 2 })],
    ]

    for (const [server, guestItem, offer] of cases) {
      expect(serverCartLines(serverCart(serverLine(server)))[0].notices).toEqual(reconcileCart([guestItem], [offer])[0].notices)
    }
  })

  it('totals only purchasable lines', () => {
    const lines = serverCartLines(serverCart(serverLine({ quantity: 2, unit_price: eur(1500) }), serverLine({ id: 11, offer_id: 2, quantity: 3, stock: 1 })))

    expect(cartTotal(lines)).toEqual(eur(3000))
  })
})
