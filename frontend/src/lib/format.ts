import type { Money } from './api/types'

const formatters = new Map<string, Intl.NumberFormat>()

/** "€12.34", matching how eneba.com shows EUR prices. */
export function formatMoney(money: Money): string {
  let formatter = formatters.get(money.currency)

  if (formatter === undefined) {
    formatter = new Intl.NumberFormat('en-IE', { style: 'currency', currency: money.currency })
    formatters.set(money.currency, formatter)
  }

  return formatter.format(money.amount_cents / 100)
}
