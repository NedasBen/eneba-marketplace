import type { CartLine } from './cartReconcile'

/**
 * Idempotency keys for POST /checkout: one per cart as it stands. Retrying
 * the same cart (a double click, a timeout) reuses the key, so the server
 * returns the same order; any change to the cart gets a new key.
 */
export function createCheckoutKeys(newId: () => string = () => crypto.randomUUID()) {
  let current: { signature: string; key: string } | null = null

  return {
    keyFor(lines: Pick<CartLine, 'offerId' | 'quantity'>[]): string {
      const signature = lines
        .map((line) => `${line.offerId}x${line.quantity}`)
        .sort()
        .join(',')

      if (current?.signature !== signature) {
        current = { signature, key: newId() }
      }
      return current.key
    },
    /** After an order was created from it, the next checkout is a new one. */
    reset(): void {
      current = null
    },
  }
}

export const checkoutKeys = createCheckoutKeys()
