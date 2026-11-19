import { describe, expect, it } from 'vitest'
import { parseShortages } from './api/orders'
import { cardDigits, checkCardFields, formatCardNumber, formatExpiry, isExpired, parseExpiry, passesLuhn, TEST_CARDS } from './card'
import { createCheckoutKeys } from './checkoutKey'
import { clockOffsetMs, formatCountdown, secondsLeft } from './countdown'

describe('card helpers', () => {
  it('formats a card number in groups of four and strips anything else', () => {
    expect(formatCardNumber('4242424242424242')).toBe('4242 4242 4242 4242')
    expect(formatCardNumber('4242-4242 42')).toBe('4242 4242 42')
    expect(cardDigits('4242 4242 4242 4242')).toBe('4242424242424242')
  })

  it('accepts every test card and rejects typos with the Luhn check', () => {
    for (const card of TEST_CARDS) expect(passesLuhn(card.number)).toBe(true)
    expect(passesLuhn('4242 4242 4242 4241')).toBe(false)
    expect(passesLuhn('4242')).toBe(false)
  })

  it('formats and parses the expiry', () => {
    expect(formatExpiry('1228')).toBe('12/28')
    expect(formatExpiry('1')).toBe('1')
    expect(parseExpiry('12/28')).toEqual({ month: 12, year: 2028 })
    expect(parseExpiry('13/28')).toBeNull()
    expect(parseExpiry('1228')).toBeNull()
  })

  it('treats a card as valid through the end of its expiry month', () => {
    const now = new Date(2026, 10, 18) // November 2026
    expect(isExpired({ month: 11, year: 2026 }, now)).toBe(false)
    expect(isExpired({ month: 10, year: 2026 }, now)).toBe(true)
    expect(isExpired({ month: 1, year: 2027 }, now)).toBe(false)
  })
})

describe('countdown', () => {
  it('counts down on the server clock, not the device clock', () => {
    // The device clock is 2 minutes behind the server.
    const receivedAt = Date.parse('2026-11-18T12:00:00Z')
    const offset = clockOffsetMs('2026-11-18T12:02:00Z', receivedAt)

    expect(secondsLeft('2026-11-18T12:17:00Z', offset, receivedAt)).toBe(15 * 60)
    expect(secondsLeft('2026-11-18T12:17:00Z', offset, receivedAt + 14 * 60_000 + 30_000)).toBe(30)
    expect(secondsLeft('2026-11-18T12:17:00Z', offset, receivedAt + 20 * 60_000)).toBe(0)
  })

  it('formats minutes and seconds', () => {
    expect(formatCountdown(900)).toBe('15:00')
    expect(formatCountdown(754)).toBe('12:34')
    expect(formatCountdown(5)).toBe('0:05')
  })
})

describe('checkout keys', () => {
  const sequence = () => {
    let next = 0
    return () => `key-${++next}`
  }

  it('reuses the key for the same cart, in any order, and replaces it when the cart changes', () => {
    const keys = createCheckoutKeys(sequence())
    const cart = [
      { offerId: 1, quantity: 2 },
      { offerId: 2, quantity: 1 },
    ]

    expect(keys.keyFor(cart)).toBe('key-1')
    expect(keys.keyFor([...cart].reverse())).toBe('key-1')
    expect(keys.keyFor([{ offerId: 1, quantity: 3 }, cart[1]])).toBe('key-2')
  })

  it('starts a new key after an order was created', () => {
    const keys = createCheckoutKeys(sequence())
    const cart = [{ offerId: 1, quantity: 1 }]

    expect(keys.keyFor(cart)).toBe('key-1')
    keys.reset()
    expect(keys.keyFor(cart)).toBe('key-2')
  })
})

describe('checkCardFields', () => {
  const now = new Date(2026, 10, 19)
  const valid = { number: '4242 4242 4242 4242', expiry: '12/28', cvc: '123', name: '  Demo Buyer ' }

  it('returns the card ready to send when every field is valid', () => {
    expect(checkCardFields(valid, now)).toEqual({
      errors: {},
      card: { card_number: '4242424242424242', expiry_month: 12, expiry_year: 2028, cvc: '123', cardholder_name: 'Demo Buyer' },
    })
  })

  it('returns per-field errors and no card otherwise', () => {
    expect(checkCardFields({ number: '4242 4242 4242 4241', expiry: '13/28', cvc: '12', name: ' ' }, now)).toEqual({
      errors: { number: 'Check the card number.', expiry: 'Use MM/YY.', cvc: '3 digits.', name: 'Enter the name on the card.' },
      card: null,
    })
    expect(checkCardFields({ ...valid, expiry: '10/26' }, now)).toEqual({ errors: { expiry: 'This card has expired.' }, card: null })
  })
})

describe('parseShortages', () => {
  const shortage = { offer_id: 7, product_name: 'Timberborn', seller_name: 'KeyHub', requested: 3, available: 1 }

  it('reads the shortages of a 409 checkout body', () => {
    expect(parseShortages({ message: 'Not enough stock.', shortages: [shortage] })).toEqual([shortage])
  })

  it('drops malformed entries and tolerates a missing or wrong-typed list', () => {
    expect(parseShortages({ shortages: [shortage, null, 'x', { ...shortage, available: '1' }, { offer_id: 8 }] })).toEqual([shortage])
    expect(parseShortages({})).toEqual([])
    expect(parseShortages({ shortages: { 0: shortage } })).toEqual([])
  })
})
