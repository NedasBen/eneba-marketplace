import type { Money } from '../../lib/api/types'
import { cn } from '../../lib/cn'
import { formatMoney } from '../../lib/format'

interface PriceTagProps {
  price: Money | null
  retailPrice?: Money
  discountPercent?: number | null
  /** `card`: large price under a "From €x -y%" line; `compact`: right-aligned, for search rows. */
  size?: 'card' | 'compact'
  className?: string
}

/**
 * Eneba's price block: "From ~~€19.99~~ -55%" in muted/lime, then the price in
 * large white bold. Shows "Sold out" when there is no in-stock offer.
 */
export function PriceTag({ price, retailPrice, discountPercent, size = 'card', className }: PriceTagProps) {
  const compact = size === 'compact'

  if (price === null) {
    return <p className={cn('font-extrabold text-muted', compact ? 'text-sm' : 'text-base', className)}>Sold out</p>
  }

  const showRetail = retailPrice !== undefined && retailPrice.amount_cents > price.amount_cents

  return (
    <div className={cn('flex flex-col', compact && 'items-end text-right', className)}>
      <p className={cn('font-extrabold leading-tight', compact ? 'text-[11px]' : 'text-sm')}>
        <span className="text-muted">From </span>
        {showRetail && (
          <>
            <s className="text-muted">{formatMoney(retailPrice)}</s>
            {!compact && discountPercent !== null && discountPercent !== undefined && discountPercent > 0 && (
              <span className="text-discount"> -{discountPercent}%</span>
            )}
          </>
        )}
      </p>
      <p className={cn('font-extrabold leading-none text-white', compact ? 'mt-[3px] text-base' : 'mt-1 text-2xl')}>{formatMoney(price)}</p>
    </div>
  )
}
