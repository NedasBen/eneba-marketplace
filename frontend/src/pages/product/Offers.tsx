import { useState } from 'react'
import { Link } from 'react-router'
import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import type { Money, ProductDetail, ProductOffer } from '../../lib/api/types'
import { formatMoney } from '../../lib/format'
import { sellerRating } from '../../lib/productDisplay'
import { AddToCartButton } from './AddToCartButton'

const INITIAL_OFFERS = 5
const VERIFIED_RATING = 97

const discountAgainst = (retail: Money, price: Money) =>
  retail.amount_cents > price.amount_cents ? Math.round((100 * (retail.amount_cents - price.amount_cents)) / retail.amount_cents) : 0

function StarIcon() {
  return (
    <svg aria-hidden viewBox="0 0 20 20" className="size-[13px] fill-accent">
      <path d="m10 1.5 2.6 5.5 6 .8-4.4 4.1 1.1 6L10 15l-5.3 2.9 1.1-6L1.4 7.8l6-.8z" />
    </svg>
  )
}

function VerifiedIcon() {
  return (
    <svg aria-label="Trusted seller" role="img" viewBox="0 0 20 20" className="size-[14px] fill-success">
      <path d="M10 1 12.4 3l3.1-.2.6 3.1 2.6 1.8-1.3 2.8 1.3 2.9-2.6 1.7-.6 3.1-3.1-.2L10 19l-2.4-2-3.1.2-.6-3.1-2.6-1.7 1.3-2.9L1.3 6.7l2.6-1.8.6-3.1 3.1.2z" />
      <path d="m6.5 10 2.3 2.3 4.7-4.7" fill="none" stroke="#1f0a4d" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

export function SellerLine({ offer }: { offer: ProductOffer }) {
  const rating = sellerRating(offer.seller.rating)

  return (
    <div className="flex min-w-0 flex-col gap-1">
      <p className="flex items-center gap-1 text-sm font-extrabold">
        <span className="truncate">{offer.seller.name}</span>
        {offer.seller.rating >= VERIFIED_RATING && <VerifiedIcon />}
      </p>
      <p className="flex flex-wrap items-center gap-1 text-xs text-soft">
        <StarIcon />
        <span className="font-extrabold text-white">{rating.score}</span> {rating.label} rating
        <span className="text-soft">· {offer.seller.feedback_count.toLocaleString('en-IE')} reviews</span>
      </p>
    </div>
  )
}

function OfferRow({ product, offer, isBest }: { product: ProductDetail; offer: ProductOffer; isBest: boolean }) {
  const discount = discountAgainst(product.retail_price, offer.price)

  return (
    <li className="flex flex-wrap items-center gap-3 bg-band px-3 py-3 sm:flex-nowrap">
      <span aria-hidden className="grid size-9 shrink-0 place-items-center rounded-full bg-white/10 text-sm font-extrabold">
        {offer.seller.name.slice(0, 1)}
      </span>
      <div className="min-w-0 flex-1">
        <SellerLine offer={offer} />
      </div>
      <div className="ml-auto flex flex-col items-end gap-1 text-right">
        {isBest && <Badge variant="highlight">Best price</Badge>}
        {discount > 0 && (
          <p className="text-xs font-extrabold">
            <s className="text-muted">{formatMoney(product.retail_price)}</s> <span className="text-discount">-{discount}%</span>
          </p>
        )}
        <p className="text-lg leading-none font-extrabold">{formatMoney(offer.price)}</p>
        <p className="text-[11px] text-muted">{offer.stock} in stock</p>
      </div>
      <div className="flex w-full flex-col items-end gap-1 sm:w-auto">
        <AddToCartButton product={product} offer={offer} variant="outline" size="sm" />
      </div>
    </li>
  )
}

/** Every purchasable offer, cheapest first, with "Load more" like eneba.com. */
export function OffersSection({ product }: { product: ProductDetail }) {
  const [showAll, setShowAll] = useState(false)
  const offers = product.offers
  const visible = showAll ? offers : offers.slice(0, INITIAL_OFFERS)
  const hidden = offers.length - visible.length

  return (
    <section id="offers" aria-labelledby="offers-heading" className="flex scroll-mt-4 flex-col gap-3">
      <h2 id="offers-heading" className="text-lg font-extrabold">
        <span className="text-success">{offers.length}</span> {offers.length === 1 ? 'offer' : 'offers'}
      </h2>

      {offers.length === 0 ? (
        <div className="flex flex-col items-start gap-2 bg-band p-4 text-sm text-soft">
          <p className="font-extrabold text-white">No sellers have this edition in stock right now.</p>
          <p>Try another platform or region above, or browse similar games.</p>
          <Link to={`/store?q=${encodeURIComponent(product.game.name)}`} className="font-extrabold text-accent hover:text-accent-hover">
            Find {product.game.name} in the store
          </Link>
        </div>
      ) : (
        <>
          <ul className="flex flex-col gap-2">
            {visible.map((offer, index) => (
              <OfferRow key={offer.id} product={product} offer={offer} isBest={index === 0} />
            ))}
          </ul>
          {hidden > 0 && (
            <div className="flex justify-center">
              <Button variant="outline" size="sm" onClick={() => setShowAll(true)}>
                Load {hidden} more {hidden === 1 ? 'offer' : 'offers'}
              </Button>
            </div>
          )}
        </>
      )}
    </section>
  )
}
