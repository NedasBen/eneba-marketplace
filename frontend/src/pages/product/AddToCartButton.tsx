import { useEffect, useRef, useState } from 'react'
import { Button } from '../../components/ui/Button'
import type { ButtonSize, ButtonVariant } from '../../components/ui/buttonStyles'
import type { ProductDetail, ProductOffer } from '../../lib/api/types'
import { cart } from '../../lib/cart'

type Feedback = 'idle' | 'added' | 'limit'

/**
 * Adds one key of an offer to the guest cart. The saved price is a display
 * snapshot only; the cart re-checks offers and checkout recomputes prices.
 */
export function AddToCartButton({
  product,
  offer,
  variant = 'primary',
  size = 'md',
  fullWidth = false,
  label = 'Add to cart',
}: {
  product: ProductDetail
  offer: ProductOffer
  variant?: ButtonVariant
  size?: ButtonSize
  fullWidth?: boolean
  label?: string
}) {
  const [feedback, setFeedback] = useState<Feedback>('idle')
  const timer = useRef<number | undefined>(undefined)

  useEffect(() => () => window.clearTimeout(timer.current), [])

  const add = () => {
    const result = cart.add(
      {
        offerId: offer.id,
        snapshot: {
          productSlug: product.slug,
          productName: product.name,
          coverImageUrl: product.game.cover_image_url,
          sellerName: offer.seller.name,
          platformName: product.platform.name,
          regionName: product.region.name,
          unitPrice: offer.price,
        },
      },
      offer.stock,
    )

    setFeedback(result.status === 'added' ? 'added' : 'limit')
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => setFeedback('idle'), 2500)
  }

  return (
    <>
      <Button variant={variant} size={size} fullWidth={fullWidth} onClick={add}>
        {feedback === 'added' ? 'Added ✓' : label}
      </Button>
      <span role="status" className="sr-only">
        {feedback === 'added' && `Added ${product.name} from ${offer.seller.name} to your cart.`}
        {feedback === 'limit' && `You already have the most keys available from ${offer.seller.name} in your cart.`}
      </span>
      {feedback === 'limit' && <span className="text-xs text-warning">Maximum for this offer is already in your cart</span>}
    </>
  )
}
