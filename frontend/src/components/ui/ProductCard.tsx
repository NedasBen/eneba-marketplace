import { useState } from 'react'
import { Link } from 'react-router'
import type { ProductCard as ProductCardData } from '../../lib/api/types'
import { cn } from '../../lib/cn'
import { CARD_IMAGE_SIZES, coverImageProps } from '../../lib/steamImages'
import { Badge } from './Badge'
import { PriceTag } from './PriceTag'

/**
 * Store card modelled on eneba.com's `.pFaGHa`: square corners, portrait
 * cover (140%), activation-service strip over the image, two-line title,
 * region label, price block. Hover: teal border + dimmed cover. Sold out:
 * the whole card dims, as with `.KRC33X`.
 */
export function ProductCard({
  product,
  loading = 'lazy',
}: {
  product: ProductCardData
  /** `eager` for cards above the fold; `priority` also for the likely LCP image (one per page). */
  loading?: 'lazy' | 'eager' | 'priority'
}) {
  const [imageFailed, setImageFailed] = useState(false)

  return (
    <Link
      to={`/product/${product.slug}`}
      className={cn(
        'group relative flex flex-col bg-card outline outline-1 -outline-offset-1 outline-transparent transition-[outline-color] duration-150',
        'hover:outline-highlight focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent focus-visible:transition-none',
      )}
    >
      <div className="relative aspect-[1/1.4] overflow-hidden bg-linear-to-b from-placeholder-from to-placeholder-to">
        {imageFailed ? (
          <span className="absolute inset-0 grid place-items-center p-3 text-center text-[13px] font-semibold leading-snug text-white/55">
            {product.game.name}
          </span>
        ) : (
          <img
            {...coverImageProps(product.game.cover_image_url)}
            sizes={CARD_IMAGE_SIZES}
            alt=""
            width={300}
            height={420}
            loading={loading === 'lazy' ? 'lazy' : 'eager'}
            fetchPriority={loading === 'priority' ? 'high' : undefined}
            decoding="async"
            onError={() => setImageFailed(true)}
            className="absolute inset-0 size-full object-cover transition-opacity duration-150 group-hover:opacity-80"
          />
        )}
        <span className="absolute inset-x-0 bottom-0 bg-black/60 py-1 text-center text-xs font-semibold text-soft">
          {product.platform.activation_service}
        </span>
      </div>

      <div className="flex flex-1 flex-col gap-1 px-3 pt-2 pb-3">
        <h3 className="line-clamp-2 min-h-[3em] text-sm font-extrabold leading-normal text-white">{product.name}</h3>
        <Badge variant="region">{product.region.name}</Badge>
        <PriceTag
          className="mt-auto pt-1"
          price={product.price_from}
          retailPrice={product.retail_price}
          discountPercent={product.discount_percent}
        />
      </div>

      {!product.in_stock && <span aria-hidden className="pointer-events-none absolute inset-0 bg-card/50" />}
    </Link>
  )
}
