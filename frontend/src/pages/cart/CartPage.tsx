import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { Container } from '../../components/layout/Container'
import { Button, ButtonLink } from '../../components/ui/Button'
import { EmptyState, ErrorState } from '../../components/ui/PageState'
import { Skeleton } from '../../components/ui/Skeleton'
import { CART_QUERY_KEY } from '../../lib/api/cart'
import { ApiError } from '../../lib/api/client'
import { orderQueries, ordersApi, parseShortages, type Shortage } from '../../lib/api/orders'
import { loginPathFor } from '../../lib/auth/redirect'
import { MAX_QUANTITY } from '../../lib/cart'
import type { CartLine, CartNotice } from '../../lib/cartReconcile'
import { mergeNotices, useMergeNotices } from '../../lib/cartSession'
import { checkoutKeys } from '../../lib/checkoutKey'
import { formatMoney } from '../../lib/format'
import { useCartActions, useCartLines } from '../../lib/useCartState'

function noticeText(notice: CartNotice): string {
  switch (notice.kind) {
    case 'unavailable':
      return 'This offer is no longer available. Remove it and pick another seller.'
    case 'out_of_stock':
      return 'Out of stock since you added it.'
    case 'reduced_stock':
      return `Only ${notice.available} left. Lower the quantity to continue.`
    case 'price_changed':
      return `Price changed from ${formatMoney(notice.previous)} to ${formatMoney(notice.current)} since you added it.`
  }
}

function actionErrorText(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 422) return error.fieldError('quantity') ?? error.message
    if (error.status === 404) return 'This line was already removed. Refresh the page.'
    if (error.isNetworkError) return 'Could not reach the server. Try again.'
  }
  return 'Could not update your cart. Try again.'
}

function CartRow({ line }: { line: CartLine }) {
  const actions = useCartActions()
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const price = line.currentPrice ?? line.seenPrice
  const reduced = line.notices.find((notice) => notice.kind === 'reduced_stock')
  // Options up to what can be bought now, but always including the current quantity.
  const options = Math.max(line.quantity, line.maxQuantity ?? MAX_QUANTITY)

  const run = async (action: () => Promise<void>) => {
    setPending(true)
    setError(null)
    try {
      await action()
    } catch (caught) {
      setError(actionErrorText(caught))
    } finally {
      setPending(false)
    }
  }

  return (
    <li className="flex flex-wrap gap-3 bg-band p-3 sm:flex-nowrap" aria-busy={pending || undefined}>
      <img src={line.display.coverImageUrl} alt="" className="aspect-[1/1.4] w-[56px] shrink-0 object-cover" />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <Link to={`/product/${line.display.productSlug}`} className="font-extrabold hover:text-accent">
          {line.display.productName}
        </Link>
        <p className="text-xs text-soft">Sold by {line.display.sellerName}</p>
        {line.notices.map((notice) => (
          <p key={notice.kind} role="status" className="text-xs font-semibold text-warning">
            {noticeText(notice)}
          </p>
        ))}
        {error && (
          <p role="alert" className="text-xs font-semibold text-warning">
            {error}
          </p>
        )}
      </div>
      <div className="ml-auto flex items-center gap-3">
        <label className="flex items-center gap-1 text-xs text-muted">
          Qty
          <select
            value={line.quantity}
            disabled={pending || line.maxQuantity === 0}
            onChange={(event) => void run(() => actions.setQuantity(line, Number(event.target.value)))}
            className="bg-black/20 px-1 py-1 text-sm font-extrabold text-white disabled:opacity-50 [&>option]:bg-card"
          >
            {Array.from({ length: Math.min(MAX_QUANTITY, options) }, (_, index) => index + 1).map((quantity) => (
              <option key={quantity} value={quantity}>
                {quantity}
              </option>
            ))}
          </select>
        </label>
        {reduced && reduced.kind === 'reduced_stock' && (
          <Button size="sm" variant="secondary" disabled={pending} onClick={() => void run(() => actions.setQuantity(line, reduced.available))}>
            Set to {reduced.available}
          </Button>
        )}
        <p className="min-w-[72px] text-right font-extrabold">{formatMoney({ ...price, amount_cents: price.amount_cents * line.quantity })}</p>
        <button
          type="button"
          disabled={pending}
          onClick={() => void run(() => actions.remove(line))}
          className="text-xs font-semibold text-muted hover:text-white disabled:opacity-50"
          aria-label={`Remove ${line.display.productName}`}
        >
          Remove
        </button>
      </div>
    </li>
  )
}

/**
 * Starts checkout: reserves the keys and opens the payment page. The
 * idempotency key stays the same for an unchanged cart, so a double click
 * or a retry after a network error can't create a second order.
 */
function CheckoutButton({ lines, disabled }: { lines: CartLine[]; disabled: boolean }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [shortages, setShortages] = useState<Shortage[]>([])

  const start = async () => {
    setPending(true)
    setError(null)
    setShortages([])

    try {
      const order = await ordersApi.checkout(checkoutKeys.keyFor(lines))
      checkoutKeys.reset()
      queryClient.setQueryData(orderQueries.detail(order.reference).queryKey, order)
      await navigate(`/checkout/${order.reference}`)
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 409) {
        setShortages(parseShortages(caught.body))
        setError(caught.message)
        void queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY })
      } else if (caught instanceof ApiError && caught.status === 422) {
        setError(caught.fieldError('cart') ?? caught.message)
      } else {
        setError(caught instanceof ApiError && caught.isNetworkError ? 'Could not reach the server. Try again.' : 'Could not start checkout. Try again.')
      }
    } finally {
      setPending(false)
    }
  }

  return (
    <>
      <Button fullWidth loading={pending} disabled={disabled} onClick={() => void start()}>
        Checkout
      </Button>
      {error && (
        <div role="alert" className="flex flex-col gap-1 text-xs font-semibold text-warning">
          <p>{error}</p>
          {shortages.length > 0 && (
            <ul className="list-disc pl-4">
              {shortages.map((shortage) => (
                <li key={shortage.offer_id}>
                  {shortage.product_name} ({shortage.seller_name}):{' '}
                  {shortage.available === 0 ? 'none left' : `only ${shortage.available} left, you have ${shortage.requested}`}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </>
  )
}

/** What changed when the guest cart was merged at sign-in; shown once. */
function MergeNotices() {
  const messages = useMergeNotices()
  if (messages.length === 0) return null

  return (
    <div role="status" className="flex flex-col gap-2 border-l-4 border-accent bg-raised p-4">
      <p className="font-extrabold">We added your guest cart to your account</p>
      <ul className="flex list-disc flex-col gap-1 pl-4 text-sm text-soft">
        {messages.map((message) => (
          <li key={message}>{message}</li>
        ))}
      </ul>
      <Button size="sm" variant="secondary" className="self-start" onClick={mergeNotices.clear}>
        Got it
      </Button>
    </div>
  )
}

/**
 * The cart: the guest cart (localStorage) or the signed-in user's server
 * cart. Saved prices are display-only in both: lines show current prices,
 * flag changes and stock problems, and checkout recomputes everything.
 */
export function CartPage() {
  const view = useCartLines()
  const blocked = view.lines.some((line) => !line.purchasable)

  return (
    <Container className="flex flex-col gap-5 py-6">
      <title>Cart | Eneba Marketplace</title>
      <h1 className="text-2xl font-extrabold">Your cart</h1>
      <MergeNotices />

      {view.isLoading ? (
        <div className="flex flex-col gap-2" aria-busy>
          <Skeleton className="h-[110px] w-full" />
          <Skeleton className="h-[110px] w-full" />
        </div>
      ) : view.source === 'server' && view.isError && view.lines.length === 0 ? (
        <ErrorState title="We couldn’t load your cart" error={view.error} onRetry={view.retry} />
      ) : view.lines.length === 0 ? (
        <EmptyState title="Your cart is empty" action={<ButtonLink to="/store">Browse the store</ButtonLink>}>
          Find a game in the store and add a key from the seller you like.
        </EmptyState>
      ) : (
        <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_300px]">
          <ul className="flex flex-col gap-2" aria-busy={view.isFetching}>
            {view.lines.map((line) => (
              <CartRow key={line.offerId} line={line} />
            ))}
          </ul>
          <aside className="flex flex-col gap-3 self-start bg-card p-4">
            <p className="flex justify-between text-sm">
              <span className="text-soft">Total</span>
              <span className="text-xl font-extrabold">{view.total ? formatMoney(view.total) : '…'}</span>
            </p>
            {view.source === 'guest' && view.isError && <p className="text-xs text-warning">Couldn’t check current prices. They will be confirmed at checkout.</p>}
            {view.source === 'guest' ? (
              <ButtonLink to={loginPathFor('/cart')} fullWidth>
                Log in to check out
              </ButtonLink>
            ) : (
              <CheckoutButton lines={view.lines} disabled={blocked || view.total === null} />
            )}
            <p className="text-xs text-muted">
              {blocked ? 'Resolve the notices above to continue. ' : ''}
              {view.source === 'guest' ? 'Your cart is kept when you log in. ' : 'Your keys are reserved for 15 minutes while you pay. '}
              Prices are confirmed by the server at checkout.
            </p>
          </aside>
        </div>
      )}
    </Container>
  )
}
