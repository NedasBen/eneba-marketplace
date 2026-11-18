import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { Container } from '../../components/layout/Container'
import { Badge } from '../../components/ui/Badge'
import { ButtonLink } from '../../components/ui/Button'
import { EmptyState, ErrorState } from '../../components/ui/PageState'
import { Skeleton } from '../../components/ui/Skeleton'
import { useProduct } from '../../lib/api/catalog'
import { ApiError } from '../../lib/api/client'
import type { ProductDetail } from '../../lib/api/types'
import { cn } from '../../lib/cn'
import { coverImageProps } from '../../lib/steamImages'
import { EditionSwitcher } from './EditionSwitcher'
import { FeaturedOffer } from './FeaturedOffer'
import { OffersSection } from './Offers'
import { ProductDetails } from './ProductDetails'
import { ScreenshotGallery } from './ScreenshotGallery'

function Fact({ icon, title, children }: { icon: ReactNode; title: string; children: ReactNode }) {
  return (
    <div className="flex gap-3">
      <span aria-hidden className="mt-[2px] grid size-8 shrink-0 place-items-center text-white">
        {icon}
      </span>
      <div className="flex flex-col gap-[2px]">
        <p className="text-sm font-extrabold">{title}</p>
        <p className="text-xs text-soft">{children}</p>
      </div>
    </div>
  )
}

const icon = (path: string) => (
  <svg viewBox="0 0 24 24" className="size-[26px]" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
    <path d={path} />
  </svg>
)

/** Cover, title, the key facts (eneba.com's 2×2 grid) and the edition switcher. */
function ProductSummary({ product }: { product: ProductDetail }) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row">
      <img
        {...coverImageProps(product.game.cover_image_url)}
        sizes="(min-width: 600px) 165px, 130px"
        width={165}
        height={231}
        fetchPriority="high"
        alt={`${product.game.name} cover`}
        className="aspect-[1/1.4] w-[130px] shrink-0 bg-card object-cover sm:w-[165px]"
      />
      <div className="flex min-w-0 flex-col gap-4">
        <div className="flex flex-col gap-2">
          <h1 className="text-2xl leading-tight font-extrabold">{product.name}</h1>
          <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{product.platform.name}</Badge>
            <Badge variant="outline">{product.platform.activation_service}</Badge>
            <Badge variant="region">{product.region.name}</Badge>
          </div>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Fact icon={icon('M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm-9 9h18M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3Z')} title={product.region.name}>
            Activation region of this key
          </Fact>
          <Fact icon={icon('M4 17V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10M2 19h20M10 9l-2 2 2 2M14 9l2 2-2 2')} title={product.platform.activation_service}>
            Activate / redeem on {product.platform.activation_service}
          </Fact>
          <Fact icon={icon('M14 7a3 3 0 1 1-3 3L4 17v3h3v-2h2v-2h2l1.5-1.5A3 3 0 0 1 14 7Z')} title="Digital key">
            A digital code, delivered instantly after payment
          </Fact>
          <Fact icon={icon('M4 5h16v11H4zM8 20h8M12 16v4')} title={`Works on ${product.platform.name}`}>
            Check the system requirements before buying
          </Fact>
        </div>
        <EditionSwitcher editions={product.editions} />
      </div>
    </div>
  )
}

function ProductPageSkeleton() {
  return (
    <div aria-hidden>
    <Container className="py-5">
      <Skeleton className="mb-4 h-[12px] w-[260px]" />
      <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_300px]">
        <div className="flex flex-col gap-5">
          <div className="flex gap-4">
            <Skeleton className="aspect-[1/1.4] w-[165px] shrink-0" />
            <div className="flex flex-1 flex-col gap-3">
              <Skeleton className="h-[28px] w-4/5" />
              <Skeleton className="h-[18px] w-1/3" />
              <Skeleton className="mt-3 h-[60px] w-full" />
            </div>
          </div>
          <div className="grid grid-cols-6 gap-2">
            {Array.from({ length: 6 }, (_, index) => (
              <Skeleton key={index} className="aspect-video" />
            ))}
          </div>
          {[0, 1, 2].map((row) => (
            <Skeleton key={row} className="h-[74px] w-full" />
          ))}
        </div>
        <Skeleton className="h-[300px] w-full" />
      </div>
    </Container>
    </div>
  )
}

function ProductNotFound() {
  return (
    <Container className="py-9">
      <title>Product not found | Eneba Marketplace</title>
      <EmptyState
        as="h1"
        title="This product doesn’t exist"
        action={
          <div className="flex flex-wrap gap-2">
            <ButtonLink to="/store">Browse the store</ButtonLink>
            <ButtonLink to="/" variant="secondary">
              Home
            </ButtonLink>
          </div>
        }
      >
        The link may be old, or the product was removed. Try searching for the game instead.
      </EmptyState>
    </Container>
  )
}

/** /product/:slug, modelled on eneba.com's product page. */
export function ProductPage() {
  const { slug = '' } = useParams()
  const product = useProduct(slug)

  if (product.isPending) return <ProductPageSkeleton />

  if (product.isError) {
    if (product.error instanceof ApiError && product.error.status === 404) return <ProductNotFound />

    return (
      <Container className="py-9">
        <title>Product | Eneba Marketplace</title>
        <ErrorState as="h1" title="We couldn’t load this product" error={product.error} onRetry={() => void product.refetch()} />
      </Container>
    )
  }

  const data = product.data
  const isSwitching = product.isPlaceholderData

  return (
    <Container className="py-5">
      <title>{`${data.name} | Eneba Marketplace`}</title>

      <nav aria-label="Breadcrumb" className="mb-4">
        <ol className="flex flex-wrap items-center gap-1 text-xs text-muted">
          <li>
            <Link to="/" className="hover:text-white">
              Home
            </Link>
          </li>
          <li aria-hidden>›</li>
          <li>
            <Link to="/store" className="hover:text-white">
              Store
            </Link>
          </li>
          <li aria-hidden>›</li>
          <li>
            <Link to={`/store?platform=${data.platform.slug}`} className="hover:text-white">
              {data.platform.name}
            </Link>
          </li>
          <li aria-hidden>›</li>
          <li aria-current="page" className="truncate text-soft">
            {data.game.name}
          </li>
        </ol>
      </nav>

      <div aria-busy={isSwitching} className={cn('grid gap-5 transition-opacity md:grid-cols-[minmax(0,1fr)_300px]', isSwitching && 'opacity-60')}>
        <div className="md:col-start-1">
          <ProductSummary product={data} />
        </div>

        {/* Second in the DOM so it follows the summary on mobile; spans the main column's rows on desktop. */}
        <aside aria-label="Buy" className="md:col-start-2 md:row-span-4 md:row-start-1">
          <div className="md:sticky md:top-4">
            <FeaturedOffer product={data} />
          </div>
        </aside>

        <div className="md:col-start-1">
          <ScreenshotGallery screenshots={data.game.screenshots} gameName={data.game.name} />
        </div>
        <div className="md:col-start-1">
          <OffersSection product={data} />
        </div>
        <div className="md:col-start-1">
          <ProductDetails product={data} />
        </div>
      </div>
    </Container>
  )
}
