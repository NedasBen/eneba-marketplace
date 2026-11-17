import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { Container } from '../../components/layout/Container'
import { Button, ButtonLink } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { ProductCard } from '../../components/ui/ProductCard'
import { ProductCardSkeleton } from '../../components/ui/Skeleton'
import { useStoreParams } from '../../hooks/useStoreParams'
import { useCatalogFilters, useProducts } from '../../lib/api/catalog'
import { ApiError } from '../../lib/api/client'
import { cn } from '../../lib/cn'
import { activeFilterCount, clearFilters, toFilterParams, toProductListParams, toQueryString, withoutInvalidParams } from '../../lib/storeParams'
import { ActiveFilters } from './ActiveFilters'
import { FilterDrawer } from './FilterDrawer'
import { FilterPanel } from './FilterPanel'
import { SortSelect } from './SortSelect'

const PER_PAGE = 24

function validationErrorOf(...errors: (Error | null)[]): ApiError | undefined {
  return errors.find((error): error is ApiError => error instanceof ApiError && error.status === 422)
}

function FiltersIcon() {
  return (
    <svg aria-hidden viewBox="0 0 24 24" className="size-[18px]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
      <path d="M4 6h16M7 12h10M10 18h4" />
    </svg>
  )
}

/**
 * /store: catalog with filters, sorting, pagination and search results.
 * All state lives in the URL (see lib/storeParams.ts).
 */
export function StorePage() {
  const { state, commit, isCanonical, navigationState } = useStoreParams()
  const [drawerOpen, setDrawerOpen] = useState(false)
  const resultsRef = useRef<HTMLElement>(null)

  const products = useProducts(toProductListParams(state, PER_PAGE))
  const filters = useCatalogFilters(toFilterParams(state))
  const facets = filters.data

  // Normalise the address bar to the canonical comma form (e.g. old `platform[]=` links).
  useEffect(() => {
    if (!isCanonical) commit(state, { replace: true })
  }, [isCanonical, state, commit])

  // Old links can point at filters that no longer exist: drop exactly what the API rejected and retry.
  const validationError = validationErrorOf(products.error, filters.error)
  const unrecoverable = validationError !== undefined && withoutInvalidParams(state, validationError.errors) === null
  useEffect(() => {
    if (validationError === undefined) return
    const recovered = withoutInvalidParams(state, validationError.errors)
    if (recovered !== null) commit(recovered, { replace: true, navigationState: { removedInvalidFilters: true } })
  }, [validationError, state, commit])

  // A shared link to page 40 of what is now 3 pages lands on the last page.
  const meta = products.data?.meta
  useEffect(() => {
    if (meta && !products.isPlaceholderData && state.page > Math.max(1, meta.last_page)) {
      commit({ ...state, page: Math.max(1, meta.last_page) }, { replace: true })
    }
  }, [meta, products.isPlaceholderData, state, commit])

  const failed = (products.isError && validationError === undefined) || unrecoverable
  const isFirstLoad = products.isPending && !failed
  const isRefreshing = products.isFetching && products.isPlaceholderData
  const items = products.data?.data ?? []
  const corrected = products.data?.query?.corrected
  const filterCount = activeFilterCount(state)

  const title = state.q ? `Results for “${state.q}”` : 'Buy the best games for less'

  return (
    <Container className="flex flex-col gap-5 py-6">
      <title>{`${state.q ? `${state.q} – search` : 'Store'} | Eneba Marketplace`}</title>

      <div className="flex flex-col gap-2">
        <nav aria-label="Breadcrumb">
          <ol className="flex items-center gap-1 text-xs text-muted">
            <li>
              <Link to="/" className="hover:text-white">
                Home
              </Link>
            </li>
            <li aria-hidden>›</li>
            <li aria-current="page" className="text-soft">
              Store
            </li>
          </ol>
        </nav>
        <h1 className="text-2xl font-extrabold md:text-3xl">{title}</h1>
        {corrected && (
          <p className="text-sm text-soft" aria-live="polite">
            Showing results for <strong className="font-extrabold text-white">{corrected}</strong>
          </p>
        )}
      </div>

      {navigationState.removedInvalidFilters && (
        <p role="status" className="border-l-4 border-accent bg-black/20 px-3 py-2 text-sm text-soft">
          Some filters from this link no longer exist and were removed.
        </p>
      )}

      <div className="grid gap-5 md:grid-cols-[270px_minmax(0,1fr)]">
        <aside aria-label="Filters" className="hidden md:block">
          {/* No endless skeleton when the filters request has failed. */}
          {!filters.isError && <FilterPanel state={state} facets={facets} onChange={commit} />}
        </aside>

        <section ref={resultsRef} aria-label="Results" aria-busy={isFirstLoad || isRefreshing} className="flex min-w-0 flex-col gap-4">
          <div className="flex flex-wrap items-center gap-3">
            <Button variant="secondary" className="md:hidden" onClick={() => setDrawerOpen(true)}>
              <FiltersIcon />
              Filters{filterCount > 0 && ` (${filterCount})`}
            </Button>
            <p className="text-sm text-muted">
              {meta ? (
                <>
                  <strong className="font-extrabold text-white">{meta.total.toLocaleString('en-IE')}</strong> {meta.total === 1 ? 'result' : 'results'}
                </>
              ) : (
                ' '
              )}
            </p>
            <div className="ml-auto">
              <SortSelect state={state} facets={facets} onChange={commit} />
            </div>
          </div>

          <ActiveFilters state={state} facets={facets} onChange={commit} />

          {failed ? (
            <div role="alert" className="flex flex-col items-start gap-3 bg-black/20 p-5">
              <h2 className="text-lg font-extrabold">We couldn’t load the store</h2>
              <p className="max-w-[60ch] text-sm text-soft">
                {products.error instanceof ApiError && products.error.isNetworkError
                  ? 'The server didn’t respond. On the free hosting tier it can take up to a minute to wake up.'
                  : 'Something went wrong on our side. Please try again.'}
              </p>
              <div className="flex gap-2">
                <Button
                  onClick={() => {
                    void products.refetch()
                    void filters.refetch()
                  }}
                >
                  Try again
                </Button>
                {unrecoverable && (
                  <ButtonLink to="/store" variant="secondary">
                    Reset filters
                  </ButtonLink>
                )}
              </div>
            </div>
          ) : isFirstLoad ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {Array.from({ length: 12 }, (_, index) => (
                <ProductCardSkeleton key={index} />
              ))}
            </div>
          ) : items.length === 0 ? (
            <div className="flex flex-col items-start gap-3 bg-black/20 p-5">
              <h2 className="text-lg font-extrabold">{state.q ? `No results for “${state.q}”` : 'No games match these filters'}</h2>
              <p className="text-sm text-soft">
                {filterCount > 0 ? 'Try removing a filter or widening the price range.' : 'Check the spelling or try a shorter search.'}
              </p>
              <div className="flex gap-2">
                {filterCount > 0 && <Button onClick={() => commit(clearFilters(state))}>Clear filters</Button>}
                {state.q && (
                  <ButtonLink to="/store" variant="secondary">
                    Browse all games
                  </ButtonLink>
                )}
              </div>
            </div>
          ) : (
            <ul className={cn('grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 lg:grid-cols-4', isRefreshing && 'opacity-60')}>
              {items.map((product) => (
                <li key={product.id} className="grid">
                  <ProductCard product={product} />
                </li>
              ))}
            </ul>
          )}

          {meta && !failed && (
            <Pagination page={state.page} lastPage={meta.last_page} hrefFor={(page) => `/store${toQueryString({ ...state, page })}`} />
          )}
        </section>
      </div>

      <FilterDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} resultCount={meta?.total}>
        <FilterPanel state={state} facets={facets} onChange={commit} />
      </FilterDrawer>
    </Container>
  )
}
