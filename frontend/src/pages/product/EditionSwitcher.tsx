import { Link } from 'react-router'
import type { ProductEdition } from '../../lib/api/types'
import { cn } from '../../lib/cn'
import { formatMoney } from '../../lib/format'
import { groupEditionsByPlatform } from '../../lib/productDisplay'

const pillClasses = (isCurrent: boolean, inStock: boolean) =>
  cn(
    'inline-flex min-h-8 items-center gap-2 border px-2 text-sm font-semibold transition-colors',
    isCurrent ? 'border-accent bg-white/10 text-white' : 'border-white/20 text-soft hover:border-white/60 hover:text-white',
    !inStock && !isCurrent && 'opacity-55',
  )

/**
 * Switch between editions of the same game: platform first, then region.
 * Every option is a real link to that edition's page.
 */
export function EditionSwitcher({ editions }: { editions: ProductEdition[] }) {
  if (editions.length <= 1) return null

  const groups = groupEditionsByPlatform(editions)
  const currentGroup = groups.find((group) => group.isCurrent) ?? groups[0]
  const current = editions.find((edition) => edition.is_current)

  // Switching platform keeps the region when that edition exists, else prefers one in stock.
  const targetFor = (group: (typeof groups)[number]) =>
    group.editions.find((edition) => edition.region.slug === current?.region.slug) ??
    group.editions.find((edition) => edition.in_stock) ??
    group.editions[0]

  return (
    <div className="flex flex-col gap-3">
      {groups.length > 1 && (
        <nav aria-label="Platform" className="flex flex-col gap-1">
          <span className="text-xs font-semibold text-muted">Platform</span>
          <ul className="flex flex-wrap gap-2">
            {groups.map((group) => (
              <li key={group.platform.slug}>
                <Link to={`/product/${targetFor(group).slug}`} preventScrollReset aria-current={group.isCurrent ? 'true' : undefined} className={pillClasses(group.isCurrent, group.inStock)}>
                  {group.platform.name}
                  {!group.inStock && <span className="text-[11px] text-muted">Sold out</span>}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      )}

      {currentGroup.editions.length > 1 && (
        <nav aria-label="Region" className="flex flex-col gap-1">
          <span className="text-xs font-semibold text-muted">Region</span>
          <ul className="flex flex-wrap gap-2">
            {currentGroup.editions.map((edition) => (
              <li key={edition.slug}>
                <Link to={`/product/${edition.slug}`} preventScrollReset aria-current={edition.is_current ? 'page' : undefined} className={pillClasses(edition.is_current, edition.in_stock)}>
                  {edition.region.name}
                  <span className="text-[11px] font-extrabold text-muted">{edition.price_from ? formatMoney(edition.price_from) : 'Sold out'}</span>
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      )}
    </div>
  )
}
