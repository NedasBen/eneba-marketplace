import { apiRequest } from './client'
import type { Money } from './types'

/** A line of the signed-in user's cart, priced from the offer's current state. */
export interface ServerCartLine {
  id: number
  offer_id: number
  quantity: number
  is_active: boolean
  stock: number
  /** min(10, stock), 0 when the offer is paused. */
  max_quantity: number
  unit_price: Money
  /** Price when the line was added; display only (the "price changed" notice). */
  price_seen: Money
  line_total: Money
  seller: { name: string }
  product: { slug: string; name: string; cover_image_url: string; platform_name: string; region_name: string }
}

export interface ServerCart {
  items: ServerCartLine[]
  item_count: number
  total: Money
}

export type MergeAdjustmentKind = 'clamped' | 'unavailable' | 'out_of_stock'

export interface MergeAdjustment {
  offer_id: number
  kind: MergeAdjustmentKind
  requested: number
  /** The line's quantity after the merge. */
  quantity: number
}

export const CART_QUERY_KEY = ['cart'] as const

export const cartApi = {
  get: (signal?: AbortSignal) => apiRequest<{ data: ServerCart }>('GET', '/cart', { signal }).then((response) => response.data),

  add: (offerId: number, quantity = 1) =>
    apiRequest<{ data: ServerCart }>('POST', '/cart/items', { body: { offer_id: offerId, quantity } }).then((response) => response.data),

  setQuantity: (lineId: number, quantity: number) =>
    apiRequest<{ data: ServerCart }>('PATCH', `/cart/items/${lineId}`, { body: { quantity } }).then((response) => response.data),

  remove: (lineId: number) => apiRequest<{ data: ServerCart }>('DELETE', `/cart/items/${lineId}`).then((response) => response.data),

  /** Idempotent per merge id: a retry returns the first result without re-adding. */
  merge: (mergeId: string, items: { offer_id: number; quantity: number }[]) =>
    apiRequest<{ data: ServerCart; adjustments: MergeAdjustment[] }>('POST', '/cart/merge', { body: { merge_id: mergeId, items } }),
}
