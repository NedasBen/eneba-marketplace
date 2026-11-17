import { useId, useState, type FormEvent, type ReactNode } from 'react'
import { Checkbox } from '../../components/ui/Checkbox'
import { Skeleton } from '../../components/ui/Skeleton'
import { Button } from '../../components/ui/Button'
import type { CatalogFilters, FacetOption } from '../../lib/api/types'
import { formatMoney } from '../../lib/format'
import { toggleFacetValue, withFilters, type FacetKey, type StoreState } from '../../lib/storeParams'

interface FilterPanelProps {
  state: StoreState
  facets: CatalogFilters | undefined
  onChange: (next: StoreState) => void
}

function FilterGroup({ title, selectedCount = 0, children }: { title: string; selectedCount?: number; children: ReactNode }) {
  const headingId = useId()

  return (
    <section aria-labelledby={headingId} className="border-b border-white/10 px-3 py-4 last:border-b-0">
      <h2 id={headingId} className="mb-2 flex items-center justify-between text-base font-extrabold">
        {title}
        {selectedCount > 0 && (
          <span className="grid size-[18px] place-items-center rounded-full bg-accent text-[11px] leading-none text-badge-ink" aria-label={`${selectedCount} selected`}>
            {selectedCount}
          </span>
        )}
      </h2>
      {children}
    </section>
  )
}

function FacetList({ facet, options, state, onChange }: { facet: FacetKey; options: FacetOption[]; state: StoreState; onChange: (next: StoreState) => void }) {
  return (
    <ul className="flex flex-col">
      {options.map((option) => {
        const checked = state[facet].includes(option.slug)

        return (
          <li key={option.slug}>
            <Checkbox
              label={option.name}
              meta={option.count}
              checked={checked}
              // Zero-result options are greyed out, but a selected one stays usable so it can be unticked.
              disabled={option.count === 0 && !checked}
              onChange={() => onChange(toggleFacetValue(state, facet, option.slug))}
            />
          </li>
        )
      })}
    </ul>
  )
}

/** Local inputs, applied on submit or blur rather than on every keystroke. */
function PriceRangeFilter({ state, facets, onChange }: { state: StoreState; facets: CatalogFilters | undefined; onChange: (next: StoreState) => void }) {
  const inputId = useId()
  const [min, setMin] = useState(state.priceMin?.toString() ?? '')
  const [max, setMax] = useState(state.priceMax?.toString() ?? '')
  const toNumber = (value: string) => (value.trim() === '' ? undefined : Number(value))
  const minValue = toNumber(min)
  const maxValue = toNumber(max)
  const invalid = minValue !== undefined && maxValue !== undefined && maxValue < minValue

  const apply = (event?: FormEvent) => {
    event?.preventDefault()
    if (invalid || (minValue === state.priceMin && maxValue === state.priceMax)) return
    onChange(withFilters(state, { priceMin: minValue || undefined, priceMax: maxValue }))
  }

  const placeholder = (money: { amount_cents: number; currency: string } | null | undefined) => (money ? formatMoney(money).replace('€', '') : '')

  return (
    <form onSubmit={apply} className="flex flex-col gap-2">
      <div className="flex items-center gap-2">
        <label className="sr-only" htmlFor={`${inputId}-min`}>
          Minimum price in euros
        </label>
        <input
          id={`${inputId}-min`}
          type="number"
          inputMode="decimal"
          min={0}
          step="0.01"
          value={min}
          placeholder={facets?.price.min ? `from ${placeholder(facets.price.min)}` : 'Min'}
          onChange={(event) => setMin(event.target.value)}
          onBlur={() => apply()}
          className="h-8 w-full min-w-0 bg-black/20 px-2 text-sm font-semibold text-white shadow-input outline-none placeholder:text-muted focus:bg-black/30"
        />
        <span aria-hidden className="text-muted">
          –
        </span>
        <label className="sr-only" htmlFor={`${inputId}-max`}>
          Maximum price in euros
        </label>
        <input
          id={`${inputId}-max`}
          type="number"
          inputMode="decimal"
          min={0}
          step="0.01"
          value={max}
          placeholder={facets?.price.max ? `to ${placeholder(facets.price.max)}` : 'Max'}
          onChange={(event) => setMax(event.target.value)}
          onBlur={() => apply()}
          aria-invalid={invalid || undefined}
          className="h-8 w-full min-w-0 bg-black/20 px-2 text-sm font-semibold text-white shadow-input outline-none placeholder:text-muted focus:bg-black/30 aria-invalid:outline-1 aria-invalid:outline-warning"
        />
      </div>
      {invalid && <p className="text-xs text-warning">The maximum must be at least the minimum.</p>}
      <Button type="submit" variant="secondary" size="sm" disabled={invalid}>
        Apply
      </Button>
    </form>
  )
}

function PanelSkeleton() {
  return (
    <div aria-hidden className="flex flex-col gap-3 p-3">
      {[0, 1, 2].map((group) => (
        <div key={group} className="flex flex-col gap-2 py-2">
          <Skeleton className="h-[16px] w-1/2" />
          {[0, 1, 2, 3].map((row) => (
            <Skeleton key={row} className="h-[14px] w-full" />
          ))}
        </div>
      ))}
    </div>
  )
}

/** The store's filter sidebar (also rendered inside the mobile drawer). */
export function FilterPanel({ state, facets, onChange }: FilterPanelProps) {
  if (facets === undefined) {
    return <PanelSkeleton />
  }

  return (
    <div className="bg-black/15">
      <FilterGroup title="Price range (EUR)" selectedCount={state.priceMin !== undefined || state.priceMax !== undefined ? 1 : 0}>
        {/* Remount when the URL's prices change (e.g. "Clear all"), resetting the inputs. */}
        <PriceRangeFilter key={`${state.priceMin}-${state.priceMax}`} state={state} facets={facets} onChange={onChange} />
      </FilterGroup>

      <FilterGroup title="Platform" selectedCount={state.platform.length}>
        <FacetList facet="platform" options={facets.platforms} state={state} onChange={onChange} />
      </FilterGroup>

      <FilterGroup title="Region" selectedCount={state.region.length}>
        <FacetList facet="region" options={facets.regions} state={state} onChange={onChange} />
      </FilterGroup>

      <FilterGroup title="Genre" selectedCount={state.genre.length}>
        <FacetList facet="genre" options={facets.genres} state={state} onChange={onChange} />
      </FilterGroup>

      <FilterGroup title="Availability" selectedCount={state.inStock ? 0 : 1}>
        <Checkbox label="Include sold-out games" checked={!state.inStock} onChange={() => onChange(withFilters(state, { inStock: !state.inStock }))} />
      </FilterGroup>
    </div>
  )
}
