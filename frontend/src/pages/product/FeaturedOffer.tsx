import { Link } from 'react-router'
import type { ProductDetail } from '../../lib/api/types'
import { formatMoney } from '../../lib/format'
import { AddToCartButton } from './AddToCartButton'
import { SellerLine } from './Offers'

/**
 * The sticky buy box (eneba.com's "Featured offer"): the cheapest offer with
 * a big "Add to cart". When the edition is sold out it points to editions
 * that are in stock instead.
 */
export function FeaturedOffer({ product }: { product: ProductDetail }) {
  const [best, ...others] = product.offers

  if (best === undefined) {
    const inStockEditions = product.editions.filter((edition) => edition.in_stock && !edition.is_current).slice(0, 4)

    return (
      <div className="flex flex-col gap-3 bg-raised p-4">
        <p className="text-xs font-extrabold text-accent uppercase">Sold out</p>
        <p className="text-sm text-soft">No seller has this edition in stock at the moment.</p>
        {inStockEditions.length > 0 && (
          <div className="flex flex-col gap-2">
            <p className="text-sm font-extrabold">Available editions</p>
            <ul className="flex flex-col gap-1">
              {inStockEditions.map((edition) => (
                <li key={edition.slug}>
                  <Link to={`/product/${edition.slug}`} preventScrollReset className="flex justify-between gap-3 bg-white/10 px-2 py-2 text-sm hover:bg-white/25">
                    <span className="font-semibold">
                      {edition.platform.name} · {edition.region.name}
                    </span>
                    {edition.price_from && <span className="font-extrabold">{formatMoney(edition.price_from)}</span>}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    )
  }

  const cheapestOther = others.at(0)

  return (
    <div className="flex flex-col gap-3 bg-raised p-4">
      <p className="text-[11px] font-extrabold text-accent uppercase">Best offer</p>
      <div>
        {product.discount_percent !== null && product.discount_percent > 0 && (
          <p className="text-sm font-extrabold">
            <s className="text-muted">{formatMoney(product.retail_price)}</s> <span className="text-discount">-{product.discount_percent}%</span>
          </p>
        )}
        <p className="text-[32px] leading-tight font-extrabold">{formatMoney(best.price)}</p>
        <p className="text-xs text-soft">
          {best.stock} in stock · Instant digital delivery
        </p>
      </div>
      <SellerLine offer={best} />
      <div className="flex flex-col gap-1">
        <AddToCartButton product={product} offer={best} fullWidth />
      </div>
      {cheapestOther && (
        <a href="#offers" className="border-t border-white/15 pt-3 text-center text-sm font-semibold text-success hover:text-white">
          +{others.length} {others.length === 1 ? 'offer' : 'offers'} from {formatMoney(cheapestOther.price)}
        </a>
      )}
    </div>
  )
}
