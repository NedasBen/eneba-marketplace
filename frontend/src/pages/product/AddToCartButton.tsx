import { useEffect, useRef, useState } from 'react'
import { Button } from '../../components/ui/Button'
import type { ButtonSize, ButtonVariant } from '../../components/ui/buttonStyles'
import type { ProductDetail, ProductOffer } from '../../lib/api/types'
import { useCartActions } from '../../lib/useCartState'

type Feedback = { kind: 'idle' } | { kind: 'added' } | { kind: 'limit'; message: string } | { kind: 'error'; message: string }

/**
 * Adds one key of an offer to the cart: the guest cart, or the server cart
 * when signed in (which enforces the caps itself). Any saved price is a
 * display snapshot only; checkout recomputes prices.
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
  const actions = useCartActions()
  const [feedback, setFeedback] = useState<Feedback>({ kind: 'idle' })
  const [pending, setPending] = useState(false)
  const timer = useRef<number | undefined>(undefined)

  useEffect(() => () => window.clearTimeout(timer.current), [])

  const add = async () => {
    setPending(true)
    const result = await actions.add(
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

    setPending(false)
    setFeedback(result.status === 'added' ? { kind: 'added' } : { kind: result.status === 'limit_reached' ? 'limit' : 'error', message: result.message })
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => setFeedback({ kind: 'idle' }), 2500)
  }

  return (
    <>
      <Button variant={variant} size={size} fullWidth={fullWidth} loading={pending || !actions.ready} onClick={() => void add()}>
        {feedback.kind === 'added' ? 'Added ✓' : label}
      </Button>
      <span role="status" className="sr-only">
        {feedback.kind === 'added' && `Added ${product.name} from ${offer.seller.name} to your cart.`}
        {(feedback.kind === 'limit' || feedback.kind === 'error') && feedback.message}
      </span>
      {(feedback.kind === 'limit' || feedback.kind === 'error') && <span className="text-xs text-warning">{feedback.message}</span>}
    </>
  )
}
