import { Link } from 'react-router'
import { Container } from '../../components/layout/Container'
import { Button, ButtonLink } from '../../components/ui/Button'
import { useOfferStates } from '../../lib/api/catalog'
import { cart, MAX_QUANTITY, useCart } from '../../lib/cart'
import { cartTotal, reconcileCart, type CartLine, type CartNotice } from '../../lib/cartReconcile'
import { formatMoney } from '../../lib/format'

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

function CartRow({ line }: { line: CartLine }) {
  const { item } = line
  const price = line.currentPrice ?? item.snapshot.unitPrice
  const reduced = line.notices.find((notice) => notice.kind === 'reduced_stock')

  return (
    <li className="flex flex-wrap gap-3 bg-band p-3 sm:flex-nowrap">
      <img src={item.snapshot.coverImageUrl} alt="" className="aspect-[1/1.4] w-[56px] shrink-0 object-cover" />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <Link to={`/product/${item.snapshot.productSlug}`} className="font-extrabold hover:text-accent">
          {item.snapshot.productName}
        </Link>
        <p className="text-xs text-soft">Sold by {item.snapshot.sellerName}</p>
        {line.notices.map((notice) => (
          <p key={notice.kind} role="status" className="text-xs font-semibold text-warning">
            {noticeText(notice)}
          </p>
        ))}
      </div>
      <div className="ml-auto flex items-center gap-3">
        <label className="flex items-center gap-1 text-xs text-muted">
          Qty
          <select
            value={item.quantity}
            onChange={(event) => cart.setQuantity(item.offerId, Number(event.target.value))}
            className="bg-black/20 px-1 py-1 text-sm font-extrabold text-white [&>option]:bg-card"
          >
            {Array.from({ length: MAX_QUANTITY }, (_, index) => index + 1).map((quantity) => (
              <option key={quantity} value={quantity}>
                {quantity}
              </option>
            ))}
          </select>
        </label>
        {reduced && reduced.kind === 'reduced_stock' && (
          <Button size="sm" variant="secondary" onClick={() => cart.setQuantity(item.offerId, reduced.available)}>
            Set to {reduced.available}
          </Button>
        )}
        <p className="min-w-[72px] text-right font-extrabold">{formatMoney({ ...price, amount_cents: price.amount_cents * item.quantity })}</p>
        <button type="button" onClick={() => cart.remove(item.offerId)} className="text-xs font-semibold text-muted hover:text-white" aria-label={`Remove ${item.snapshot.productName}`}>
          Remove
        </button>
      </div>
    </li>
  )
}

/**
 * Guest cart. Saved prices are display snapshots: every visit re-checks the
 * offers (GET /offers) and flags price changes and stock problems; the
 * server recomputes all prices at checkout regardless.
 */
export function CartPage() {
  const items = useCart()
  const offers = useOfferStates(items.map((item) => item.offerId))
  const lines = reconcileCart(items, offers.data)
  const total = cartTotal(lines)
  const blocked = lines.some((line) => !line.purchasable)

  return (
    <Container className="flex flex-col gap-5 py-6">
      <title>Cart | Eneba Marketplace</title>
      <h1 className="text-2xl font-extrabold">Your cart</h1>

      {items.length === 0 ? (
        <div className="flex flex-col items-start gap-3 bg-band p-5">
          <p className="text-soft">Your cart is empty.</p>
          <ButtonLink to="/store">Browse the store</ButtonLink>
        </div>
      ) : (
        <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_300px]">
          <ul className="flex flex-col gap-2" aria-busy={offers.isFetching}>
            {lines.map((line) => (
              <CartRow key={line.item.offerId} line={line} />
            ))}
          </ul>
          <aside className="flex flex-col gap-3 self-start bg-raised p-4">
            <p className="flex justify-between text-sm">
              <span className="text-soft">Total</span>
              <span className="text-xl font-extrabold">{total ? formatMoney(total) : '…'}</span>
            </p>
            {offers.isError && <p className="text-xs text-warning">Couldn’t check current prices. They will be confirmed at checkout.</p>}
            <Button fullWidth disabled>
              Checkout
            </Button>
            <p className="text-xs text-muted">
              {blocked ? 'Resolve the notices above to continue. ' : ''}Prices are confirmed by the server at checkout. Checkout arrives with sign-in.
            </p>
          </aside>
        </div>
      )}
    </Container>
  )
}
