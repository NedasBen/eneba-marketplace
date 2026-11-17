import type { CatalogFilters, ProductSortValue } from '../../lib/api/types'
import type { StoreState } from '../../lib/storeParams'

/**
 * Native <select> styled like Eneba's "Popularity: Most popular" control:
 * keyboard and screen-reader behaviour come for free. Options come from
 * /catalog/filters; "Relevance" only appears with a search query.
 */
export function SortSelect({ state, facets, onChange }: { state: StoreState; facets: CatalogFilters | undefined; onChange: (next: StoreState) => void }) {
  if (facets === undefined) return null

  const options = facets.sorts.filter((sort) => !sort.requires_query || state.q !== undefined)
  const defaultSort: ProductSortValue = state.q !== undefined ? 'relevance' : facets.default_sort === 'relevance' ? 'popularity' : facets.default_sort
  const value = state.sort ?? defaultSort

  return (
    <label className="relative flex items-center gap-2 text-sm font-extrabold">
      <svg aria-hidden viewBox="0 0 24 24" className="size-[20px]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
        <path d="M7 4v16M7 20l-3-3M7 20l3-3M13 6h8M13 11h6M13 16h4" />
      </svg>
      <span className="sr-only">Sort by</span>
      <select
        value={value}
        onChange={(event) => {
          const next = event.target.value as ProductSortValue
          onChange({ ...state, sort: next === defaultSort ? undefined : next, page: 1 })
        }}
        className="cursor-pointer appearance-none bg-transparent py-1 pr-5 font-extrabold text-white outline-none focus-visible:outline-2 focus-visible:outline-accent [&>option]:bg-card"
      >
        {options.map((sort) => (
          <option key={sort.value} value={sort.value}>
            {sort.label}
          </option>
        ))}
      </select>
      <svg aria-hidden viewBox="0 0 24 24" className="pointer-events-none absolute right-0 size-[16px]" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round">
        <path d="m6 9 6 6 6-6" />
      </svg>
    </label>
  )
}
