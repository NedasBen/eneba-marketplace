import type { ReactNode } from 'react'
import { Badge } from '../../components/ui/Badge'
import { Button, ButtonLink } from '../../components/ui/Button'
import { PriceTag } from '../../components/ui/PriceTag'
import { ProductCard } from '../../components/ui/ProductCard'
import { ProductCardSkeleton, Skeleton } from '../../components/ui/Skeleton'
import { useProducts } from '../../lib/api/catalog'
import type { ProductCard as ProductCardData } from '../../lib/api/types'

const STEAM = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps'
const eur = (amount_cents: number) => ({ amount_cents, currency: 'EUR' })

const fixtures: ProductCardData[] = [
  {
    id: 1,
    name: 'ELDEN RING Steam Key GLOBAL',
    slug: 'elden-ring-steam-key-global',
    game: { name: 'ELDEN RING', slug: 'elden-ring', cover_image_url: `${STEAM}/1245620/library_600x900_2x.jpg` },
    platform: { slug: 'pc', name: 'PC', activation_service: 'Steam' },
    region: { slug: 'global', name: 'GLOBAL' },
    price_from: eur(3489),
    retail_price: eur(5999),
    discount_percent: 42,
    offers_count: 4,
    in_stock: true,
  },
  {
    id: 2,
    name: 'The Witcher 3: Wild Hunt — Remastered Nintendo eShop Key EUROPE',
    slug: 'the-witcher-3-nintendo-eshop-key-europe',
    game: { name: 'The Witcher 3: Wild Hunt — Remastered', slug: 'the-witcher-3', cover_image_url: `${STEAM}/292030/library_600x900_2x.jpg` },
    platform: { slug: 'nintendo-switch', name: 'Nintendo Switch', activation_service: 'Nintendo eShop' },
    region: { slug: 'europe', name: 'EUROPE' },
    price_from: eur(1219),
    retail_price: eur(3999),
    discount_percent: 70,
    offers_count: 2,
    in_stock: true,
  },
  {
    id: 3,
    name: 'Hades II Steam Key EUROPE',
    slug: 'hades-ii-steam-key-europe',
    game: { name: 'Hades II', slug: 'hades-ii', cover_image_url: `${STEAM}/1145350/library_600x900_2x.jpg` },
    platform: { slug: 'pc', name: 'PC', activation_service: 'Steam' },
    region: { slug: 'europe', name: 'EUROPE' },
    price_from: eur(2899),
    retail_price: eur(2899),
    discount_percent: 0,
    offers_count: 3,
    in_stock: true,
  },
  {
    id: 4,
    name: 'Borderlands 2 Xbox Live Key EUROPE',
    slug: 'borderlands-2-xbox-live-key-europe',
    game: { name: 'Borderlands 2', slug: 'borderlands-2', cover_image_url: `${STEAM}/49520/library_600x900_2x.jpg` },
    platform: { slug: 'xbox', name: 'Xbox', activation_service: 'Xbox Live' },
    region: { slug: 'europe', name: 'EUROPE' },
    price_from: null,
    retail_price: eur(2999),
    discount_percent: null,
    offers_count: 0,
    in_stock: false,
  },
  {
    id: 5,
    name: 'Missing Cover Art Edition Steam Key GLOBAL',
    slug: 'missing-cover',
    game: { name: 'Missing Cover Art Edition', slug: 'missing-cover', cover_image_url: 'https://invalid.example/cover.jpg' },
    platform: { slug: 'pc', name: 'PC', activation_service: 'Steam' },
    region: { slug: 'global', name: 'GLOBAL' },
    price_from: eur(499),
    retail_price: eur(1999),
    discount_percent: 75,
    offers_count: 1,
    in_stock: true,
  },
]

const swatches: [string, string][] = [
  ['bg-page', 'page #4618ac'],
  ['bg-card', 'card #1f0a4d'],
  ['bg-footer', 'footer #150733'],
  ['bg-surface', 'surface #351183'],
  ['bg-raised', 'raised #5825cc'],
  ['bg-nav', 'nav #3c1395'],
  ['bg-search', 'search #200b4e'],
  ['bg-divider', 'divider #5930b4'],
  ['bg-accent', 'accent #fad318'],
  ['bg-accent-hover', 'accent-hover #f0a613'],
  ['bg-muted', 'muted #b3aac9'],
  ['bg-soft', 'soft #dcd8e6'],
  ['bg-success', 'success #23c299'],
  ['bg-discount', 'discount #84e916'],
  ['bg-warning', 'warning #e76a8c'],
  ['bg-sale', 'sale #d32252'],
  ['bg-highlight', 'highlight #63e3c2'],
]

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-4 border-t border-white/10 pt-6">
      <h2 className="text-lg font-extrabold">{title}</h2>
      {children}
    </section>
  )
}

function LiveProducts() {
  const products = useProducts({ per_page: 6 })

  if (products.isError) {
    return <p className="text-warning">Could not load products: {products.error.message}</p>
  }

  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
      {products.isPending
        ? Array.from({ length: 6 }, (_, index) => <ProductCardSkeleton key={index} />)
        : products.data.data.map((product) => <ProductCard key={product.id} product={product} />)}
    </div>
  )
}

/** Not linked from the UI: compare these against eneba.com before building pages. */
export function ComponentsPage() {
  return (
    <div className="flex flex-col gap-8">
      <title>Components | Eneba Marketplace</title>
      <meta name="robots" content="noindex" />

      <header className="flex flex-col gap-1">
        <h1 className="text-3xl font-extrabold">Component library</h1>
        <p className="text-muted">Every component and state, for side-by-side comparison with eneba.com. Try the header search: “wichter”, “gta 5”, “eldn ring”.</p>
      </header>

      <Section title="Colors">
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
          {swatches.map(([className, label]) => (
            <div key={label} className="flex flex-col gap-1">
              <div className={`h-9 border border-white/10 ${className}`} />
              <span className="text-xs text-soft">{label}</span>
            </div>
          ))}
        </div>
      </Section>

      <Section title="Typography (Metropolis 400 / 600 / 800)">
        <div className="flex flex-col gap-2">
          <p className="text-3xl font-extrabold">Buy the best games for less — 30px / 800</p>
          <p className="text-2xl font-extrabold">€8.90 — 24px / 800 (card price)</p>
          <p className="text-lg font-extrabold">Section heading — 20px / 800</p>
          <p className="text-base font-semibold">Body semibold — 16px / 600</p>
          <p className="text-sm">Body regular — 14px / 400</p>
          <p className="text-xs text-muted">Caption muted — 12px / 400</p>
        </div>
      </Section>

      <Section title="Buttons">
        <div className="flex flex-wrap items-center gap-3">
          <Button>Buy now</Button>
          <Button variant="secondary">Secondary</Button>
          <Button variant="purple">Purple</Button>
          <Button variant="outline">Show all results</Button>
          <Button variant="ghost">Ghost</Button>
          <ButtonLink to="/store" variant="primary">
            Link as button
          </ButtonLink>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <Button size="sm">Small primary</Button>
          <Button size="sm" variant="secondary">
            Small secondary
          </Button>
          <Button loading>Paying…</Button>
          <Button disabled>Disabled</Button>
          <div className="w-[240px]">
            <Button fullWidth>Full width</Button>
          </div>
        </div>
      </Section>

      <Section title="Badges">
        <div className="flex flex-wrap items-center gap-3">
          <Badge variant="sale">-70%</Badge>
          <Badge variant="new">NEW</Badge>
          <Badge variant="highlight">Best deal</Badge>
          <Badge variant="outline">Digital good</Badge>
          <Badge variant="outline">PC</Badge>
          <Badge variant="region">GLOBAL</Badge>
          <Badge variant="region-restricted">GLOBAL</Badge>
        </div>
      </Section>

      <Section title="Price tags">
        <div className="flex flex-wrap items-end gap-9">
          <PriceTag price={eur(890)} retailPrice={eur(1999)} discountPercent={55} />
          <PriceTag price={eur(2899)} retailPrice={eur(2899)} discountPercent={0} />
          <PriceTag price={null} retailPrice={eur(2999)} />
          <div className="w-[220px] bg-page p-2">
            <PriceTag size="compact" price={eur(1416)} retailPrice={eur(4999)} />
          </div>
          <div className="w-[220px] bg-page p-2">
            <PriceTag size="compact" price={eur(229)} />
          </div>
        </div>
      </Section>

      <Section title="Skeletons">
        <div className="flex flex-wrap items-start gap-4">
          <Skeleton className="h-[14px] w-[200px]" />
          <Skeleton className="h-[78px] w-[56px]" />
          <div className="w-[190px]">
            <ProductCardSkeleton />
          </div>
        </div>
      </Section>

      <Section title="Product cards (fixtures: discount, long title, no discount, sold out, broken image)">
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
          {fixtures.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </Section>

      <Section title="Product cards (live from GET /api/v1/products)">
        <LiveProducts />
      </Section>
    </div>
  )
}
