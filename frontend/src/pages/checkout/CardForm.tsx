import { useState, type FormEvent } from 'react'
import { Button } from '../../components/ui/Button'
import { TextField } from '../../components/ui/TextField'
import type { CardInput } from '../../lib/api/orders'
import { cardDigits, formatCardNumber, formatExpiry, isExpired, parseExpiry, passesLuhn, sampleExpiry, TEST_CARDS } from '../../lib/card'

type Fields = { number: string; expiry: string; cvc: string; name: string }
type FieldErrors = Partial<Record<keyof Fields, string>>

/** Instant checks; the server validates everything again. */
function validate(fields: Fields): FieldErrors {
  const errors: FieldErrors = {}
  const expiry = parseExpiry(fields.expiry)

  if (!passesLuhn(fields.number)) errors.number = 'Check the card number.'
  if (expiry === null) errors.expiry = 'Use MM/YY.'
  else if (isExpired(expiry)) errors.expiry = 'This card has expired.'
  if (!/^\d{3}$/.test(fields.cvc)) errors.cvc = '3 digits.'
  if (fields.name.trim() === '') errors.name = 'Enter the name on the card.'

  return errors
}

/**
 * Simulated card form. Clicking a test card fills it in; nothing here
 * talks to a real payment provider.
 */
export function CardForm({
  submitting,
  serverErrors,
  defaultName,
  onSubmit,
}: {
  submitting: boolean
  serverErrors: Record<string, string[]>
  defaultName: string
  onSubmit: (card: CardInput) => void
}) {
  const [fields, setFields] = useState<Fields>({ number: '', expiry: '', cvc: '', name: defaultName })
  const [errors, setErrors] = useState<FieldErrors>({})

  const set = (field: keyof Fields, value: string) => {
    setFields((current) => ({ ...current, [field]: value }))
    setErrors((current) => ({ ...current, [field]: undefined }))
  }

  const fillTestCard = (number: string) => {
    setFields((current) => ({ number: formatCardNumber(number), expiry: sampleExpiry(), cvc: '123', name: current.name || defaultName }))
    setErrors({})
  }

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const found = validate(fields)
    setErrors(found)
    if (Object.keys(found).length > 0) return

    const expiry = parseExpiry(fields.expiry)!
    onSubmit({ card_number: cardDigits(fields.number), expiry_month: expiry.month, expiry_year: expiry.year, cvc: fields.cvc, cardholder_name: fields.name.trim() })
  }

  const server = (field: string) => serverErrors[field]?.[0]

  return (
    <div className="flex flex-col gap-4">
      <section aria-labelledby="test-cards-title" className="flex flex-col gap-2 border border-dashed border-white/25 p-3">
        <h3 id="test-cards-title" className="text-sm font-extrabold">
          Test cards <span className="font-semibold text-muted">(demo: no real payment)</span>
        </h3>
        <ul className="flex flex-col gap-1">
          {TEST_CARDS.map((card) => (
            <li key={card.number}>
              <button
                type="button"
                onClick={() => fillTestCard(card.number)}
                className="flex w-full flex-wrap items-center justify-between gap-x-3 px-2 py-1 text-left text-sm hover:bg-surface focus-visible:bg-surface"
              >
                <span className="font-mono font-semibold tracking-wide">{formatCardNumber(card.number)}</span>
                <span className="text-xs text-soft">{card.outcome}</span>
              </button>
            </li>
          ))}
        </ul>
        <p className="text-xs text-muted">Any future expiry date and any 3-digit CVC.</p>
      </section>

      <form onSubmit={submit} noValidate className="flex flex-col gap-3" aria-label="Card details">
        <TextField
          label="Card number"
          name="card_number"
          inputMode="numeric"
          autoComplete="cc-number"
          placeholder="1234 1234 1234 1234"
          value={fields.number}
          onChange={(event) => set('number', formatCardNumber(event.target.value))}
          error={errors.number ?? server('card_number')}
        />
        <div className="grid grid-cols-2 gap-3">
          <TextField
            label="Expiry (MM/YY)"
            name="expiry"
            inputMode="numeric"
            autoComplete="cc-exp"
            placeholder="MM/YY"
            value={fields.expiry}
            onChange={(event) => set('expiry', formatExpiry(event.target.value))}
            error={errors.expiry ?? server('expiry_month') ?? server('expiry_year')}
          />
          <TextField
            label="CVC"
            name="cvc"
            inputMode="numeric"
            autoComplete="cc-csc"
            placeholder="123"
            maxLength={3}
            value={fields.cvc}
            onChange={(event) => set('cvc', event.target.value.replace(/\D/g, '').slice(0, 3))}
            error={errors.cvc ?? server('cvc')}
          />
        </div>
        <TextField
          label="Name on card"
          name="cardholder_name"
          autoComplete="cc-name"
          value={fields.name}
          onChange={(event) => set('name', event.target.value)}
          error={errors.name ?? server('cardholder_name')}
        />
        <Button type="submit" fullWidth loading={submitting}>
          Pay now
        </Button>
      </form>
    </div>
  )
}
