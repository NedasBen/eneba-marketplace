import { queryOptions } from '@tanstack/react-query'
import { apiRequest } from './client'
import type { Money } from './types'

export type OrderStatus = 'pending' | 'paid' | 'expired'

export interface OrderKey {
  id: number
  code: string
}

export interface OrderItem {
  id: number
  product_name: string
  seller_name: string
  product_slug: string
  cover_image_url: string
  platform_name: string
  region_name: string
  quantity: number
  /** Snapshot taken at checkout. */
  unit_price: Money
  line_total: Money
  /** Only present once the order is paid. */
  keys?: OrderKey[]
}

export interface Order {
  reference: string
  /** Already "expired" for a pending order past its deadline. */
  status: OrderStatus
  total: Money
  expires_at: string
  paid_at: string | null
  created_at: string
  /** The server's clock when it answered, for the countdown. */
  server_time: string
  items: OrderItem[]
}

/** 409 from POST /checkout: lines that couldn't be fully reserved. */
export interface Shortage {
  offer_id: number
  product_name: string
  seller_name: string
  requested: number
  available: number
}

export interface CardInput {
  card_number: string
  expiry_month: number
  expiry_year: number
  cvc: string
  cardholder_name: string
}

export const orderQueries = {
  detail: (reference: string) =>
    queryOptions({
      queryKey: ['order', reference],
      queryFn: ({ signal }) => apiRequest<{ data: Order }>('GET', `/orders/${encodeURIComponent(reference)}`, { signal }).then((response) => response.data),
      staleTime: 0,
    }),
}

export const ordersApi = {
  /** Same key → same order (idempotent), so a retry never creates a second one. */
  checkout: (idempotencyKey: string) =>
    apiRequest<{ data: Order }>('POST', '/checkout', { headers: { 'Idempotency-Key': idempotencyKey } }).then((response) => response.data),

  /** 402 declined (retry allowed), 410 expired. Paying a paid order again doesn't charge twice. */
  pay: (reference: string, card: CardInput) =>
    apiRequest<{ data: Order }>('POST', `/orders/${encodeURIComponent(reference)}/pay`, { body: card }).then((response) => response.data),
}
