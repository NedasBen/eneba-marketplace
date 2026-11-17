import type { CatalogFilters } from '../../lib/api/types'
import { formatMoney } from '../../lib/format'
import { activeFilterCount, clearFilters, FACET_KEYS, toggleFacetValue, withFilters, type StoreState } from '../../lib/storeParams'

function Chip({ label, onRemove }: { label: string; onRemove: () => void }) {
  return (
    <li>
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Remove filter: ${label}`}
        className="inline-flex min-h-[28px] items-center gap-1 border border-white/10 bg-white/10 px-2 text-xs font-semibold text-white/85 transition-colors hover:border-white/40 hover:bg-white/40 hover:text-white"
      >
        {label}
        <svg aria-hidden viewBox="0 0 24 24" className="size-[12px]" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round">
          <path d="M6 6l12 12M18 6 6 18" />
        </svg>
      </button>
    </li>
  )
}

const euros = (value: number) => formatMoney({ amount_cents: Math.round(value * 100), currency: 'EUR' })

/** Removable chips for every active filter, plus "Clear all". */
export function ActiveFilters({ state, facets, onChange }: { state: StoreState; facets: CatalogFilters | undefined; onChange: (next: StoreState) => void }) {
  if (activeFilterCount(state) === 0) return null

  const names: Record<string, Map<string, string>> = {
    platform: new Map(facets?.platforms.map((option) => [option.slug, option.name])),
    region: new Map(facets?.regions.map((option) => [option.slug, option.name])),
    genre: new Map(facets?.genres.map((option) => [option.slug, option.name])),
  }

  const priceLabel =
    state.priceMin !== undefined && state.priceMax !== undefined
      ? `${euros(state.priceMin)} – ${euros(state.priceMax)}`
      : state.priceMin !== undefined
        ? `From ${euros(state.priceMin)}`
        : state.priceMax !== undefined
          ? `Up to ${euros(state.priceMax)}`
          : null

  return (
    <ul aria-label="Active filters" className="flex flex-wrap items-center gap-2">
      {FACET_KEYS.flatMap((facet) =>
        state[facet].map((slug) => (
          <Chip key={`${facet}-${slug}`} label={names[facet].get(slug) ?? slug} onRemove={() => onChange(toggleFacetValue(state, facet, slug))} />
        )),
      )}
      {priceLabel && <Chip label={priceLabel} onRemove={() => onChange(withFilters(state, { priceMin: undefined, priceMax: undefined }))} />}
      {!state.inStock && <Chip label="Including sold out" onRemove={() => onChange(withFilters(state, { inStock: true }))} />}
      <li>
        <button type="button" onClick={() => onChange(clearFilters(state))} className="px-1 text-xs font-extrabold text-accent hover:text-accent-hover">
          Clear all
        </button>
      </li>
    </ul>
  )
}
