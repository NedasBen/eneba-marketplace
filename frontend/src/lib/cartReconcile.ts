import type { Money, OfferState } from './api/types'
import type { CartItem } from './cart'

export type CartNotice =
  | { kind: 'unavailable' }
  | { kind: 'out_of_stock' }
  | { kind: 'reduced_stock'; available: number }
  | { kind: 'price_changed'; previous: Money; current: Money }

export interface CartLine {
  item: CartItem
  /** Current price from the server; null until it is known or if the offer is gone. */
  currentPrice: Money | null
  notices: CartNotice[]
  /** Whether this line can go to checkout as it stands. */
  purchasable: boolean
}

/**
 * Compare the cart's display snapshots with the offers' current state.
 * `offers` undefined means "not loaded yet": lines show their snapshot with
 * no notices and are not purchasable until confirmed.
 */
export function reconcileCart(items: CartItem[], offers: OfferState[] | undefined): CartLine[] {
  const byId = new Map(offers?.map((offer) => [offer.id, offer]))

  return items.map((item) => {
    if (offers === undefined) {
      return { item, currentPrice: null, notices: [], purchasable: false }
    }

    const offer = byId.get(item.offerId)

    if (offer === undefined || !offer.is_active) {
      return { item, currentPrice: null, notices: [{ kind: 'unavailable' }], purchasable: false }
    }

    if (offer.stock <= 0) {
      return { item, currentPrice: offer.price, notices: [{ kind: 'out_of_stock' }], purchasable: false }
    }

    const notices: CartNotice[] = []

    if (offer.stock < item.quantity) {
      notices.push({ kind: 'reduced_stock', available: offer.stock })
    }

    if (offer.price.amount_cents !== item.snapshot.unitPrice.amount_cents) {
      notices.push({ kind: 'price_changed', previous: item.snapshot.unitPrice, current: offer.price })
    }

    return { item, currentPrice: offer.price, notices, purchasable: offer.stock >= item.quantity }
  })
}

/** Total of purchasable lines at current prices (never the snapshots). */
export function cartTotal(lines: CartLine[]): Money | null {
  const counted = lines.filter((line) => line.purchasable && line.currentPrice !== null)
  if (counted.length === 0) return null

  return {
    amount_cents: counted.reduce((sum, line) => sum + (line.currentPrice?.amount_cents ?? 0) * line.item.quantity, 0),
    currency: counted[0].currentPrice?.currency ?? 'EUR',
  }
}
