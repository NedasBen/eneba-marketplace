import type { ServerCart } from './api/cart'
import type { Money, OfferState } from './api/types'
import { MAX_QUANTITY, type CartItem } from './cart'

export type CartNotice =
  | { kind: 'unavailable' }
  | { kind: 'out_of_stock' }
  | { kind: 'reduced_stock'; available: number }
  | { kind: 'price_changed'; previous: Money; current: Money }

/**
 * One cart line as the cart page shows it, whether it comes from the guest
 * cart (localStorage + GET /offers) or the server cart (GET /cart). Both go
 * through `evaluateLine`, so the notices are identical.
 */
export interface CartLine {
  offerId: number
  /** Server cart line id; null for guest lines. */
  lineId: number | null
  quantity: number
  /** Most keys this line may hold right now; null until the offer is known. */
  maxQuantity: number | null
  display: {
    productSlug: string
    productName: string
    coverImageUrl: string
    sellerName: string
    platformName: string
    regionName: string
  }
  /** Price when the line was added: display only, for the "price changed" notice. */
  seenPrice: Money
  /** Current price from the server; null until it is known or if the offer is gone. */
  currentPrice: Money | null
  notices: CartNotice[]
  /** Whether this line can go to checkout as it stands. */
  purchasable: boolean
}

interface CurrentOffer {
  is_active: boolean
  stock: number
  price: Money
}

type Evaluation = Pick<CartLine, 'currentPrice' | 'notices' | 'purchasable' | 'maxQuantity'>

/** Compare a line with its offer's current state. `null` means the offer is gone. */
function evaluateLine(quantity: number, seenPrice: Money, offer: CurrentOffer | null): Evaluation {
  if (offer === null || !offer.is_active) {
    return { currentPrice: null, notices: [{ kind: 'unavailable' }], purchasable: false, maxQuantity: 0 }
  }

  const maxQuantity = Math.max(0, Math.min(MAX_QUANTITY, offer.stock))

  if (offer.stock <= 0) {
    return { currentPrice: offer.price, notices: [{ kind: 'out_of_stock' }], purchasable: false, maxQuantity }
  }

  const notices: CartNotice[] = []

  if (offer.stock < quantity) {
    notices.push({ kind: 'reduced_stock', available: offer.stock })
  }

  if (offer.price.amount_cents !== seenPrice.amount_cents) {
    notices.push({ kind: 'price_changed', previous: seenPrice, current: offer.price })
  }

  return { currentPrice: offer.price, notices, purchasable: offer.stock >= quantity, maxQuantity }
}

/**
 * Guest cart: compare the saved display snapshots with the offers' current
 * state. `offers` undefined means "not loaded yet": lines show their
 * snapshot with no notices and are not purchasable until confirmed.
 */
export function reconcileCart(items: CartItem[], offers: OfferState[] | undefined): CartLine[] {
  const byId = new Map(offers?.map((offer) => [offer.id, offer]))

  return items.map((item) => {
    const { unitPrice, ...display } = item.snapshot
    const base = { offerId: item.offerId, lineId: null, quantity: item.quantity, display, seenPrice: unitPrice }

    if (offers === undefined) {
      return { ...base, currentPrice: null, notices: [], purchasable: false, maxQuantity: null }
    }

    return { ...base, ...evaluateLine(item.quantity, unitPrice, byId.get(item.offerId) ?? null) }
  })
}

/** Server cart: the API already sends current price and stock per line. */
export function serverCartLines(cart: ServerCart): CartLine[] {
  return cart.items.map((line) => ({
    offerId: line.offer_id,
    lineId: line.id,
    quantity: line.quantity,
    display: {
      productSlug: line.product.slug,
      productName: line.product.name,
      coverImageUrl: line.product.cover_image_url,
      sellerName: line.seller.name,
      platformName: line.product.platform_name,
      regionName: line.product.region_name,
    },
    seenPrice: line.price_seen,
    ...evaluateLine(line.quantity, line.price_seen, { is_active: line.is_active, stock: line.stock, price: line.unit_price }),
  }))
}

/** Total of purchasable lines at current prices (never the saved prices). */
export function cartTotal(lines: CartLine[]): Money | null {
  const counted = lines.filter((line) => line.purchasable && line.currentPrice !== null)
  if (counted.length === 0) return null

  return {
    amount_cents: counted.reduce((sum, line) => sum + (line.currentPrice?.amount_cents ?? 0) * line.quantity, 0),
    currency: counted[0].currentPrice?.currency ?? 'EUR',
  }
}
