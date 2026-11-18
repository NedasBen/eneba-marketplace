/**
 * Card form helpers for the simulated payment. The server validates
 * everything again; these only give instant feedback.
 */

export interface TestCard {
  number: string
  label: string
  outcome: string
}

/** Mirrors backend TestCards (docs/ARCHITECTURE.md "Simulated test cards"). */
export const TEST_CARDS: TestCard[] = [
  { number: '4242424242424242', label: 'Visa ending 4242', outcome: 'Payment succeeds' },
  { number: '4000000000000002', label: 'Visa ending 0002', outcome: 'Declined: card declined' },
  { number: '4000000000009995', label: 'Visa ending 9995', outcome: 'Declined: insufficient funds' },
]

export const cardDigits = (value: string): string => value.replace(/\D/g, '').slice(0, 19)

/** "4242424242424242" → "4242 4242 4242 4242" (groups of four, as typed). */
export function formatCardNumber(value: string): string {
  return cardDigits(value).replace(/(\d{4})(?=\d)/g, '$1 ')
}

export function passesLuhn(value: string): boolean {
  const digits = cardDigits(value)
  if (digits.length < 12) return false

  let sum = 0
  for (let index = 0; index < digits.length; index++) {
    let digit = Number(digits[digits.length - 1 - index])
    if (index % 2 === 1) {
      digit *= 2
      if (digit > 9) digit -= 9
    }
    sum += digit
  }
  return sum % 10 === 0
}

/** Typing "1228" or "12/28" → "12/28". */
export function formatExpiry(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 4)
  return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits
}

/** "MM/YY" → month and four-digit year, or null if malformed. */
export function parseExpiry(value: string): { month: number; year: number } | null {
  const match = /^(\d{2})\/(\d{2})$/.exec(value.trim())
  if (!match) return null

  const month = Number(match[1])
  if (month < 1 || month > 12) return null

  return { month, year: 2000 + Number(match[2]) }
}

/** A card is valid through the last day of its expiry month. */
export function isExpired(expiry: { month: number; year: number }, now: Date = new Date()): boolean {
  const year = now.getFullYear()
  return expiry.year < year || (expiry.year === year && expiry.month < now.getMonth() + 1)
}

/** An expiry two years out, for filling in a test card. */
export function sampleExpiry(now: Date = new Date()): string {
  return `12/${String((now.getFullYear() + 2) % 100).padStart(2, '0')}`
}
