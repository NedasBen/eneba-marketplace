import { useCallback, useMemo } from 'react'
import { useLocation, useNavigate } from 'react-router'
import { parseStoreParams, toQueryString, type StoreState } from '../lib/storeParams'

export interface StoreNavigationState {
  /** Set when an old link's invalid filters were removed automatically. */
  removedInvalidFilters?: boolean
}

/**
 * Store filters backed by the URL. Reading parses and sanitises the query
 * string; writing navigates to the canonical comma form, so Back/Forward,
 * reloads and shared links all reproduce the same view.
 */
export function useStoreParams() {
  const location = useLocation()
  const navigate = useNavigate()

  const state = useMemo(() => parseStoreParams(new URLSearchParams(location.search)), [location.search])
  const canonicalSearch = toQueryString(state)

  const commit = useCallback(
    (next: StoreState, options: { replace?: boolean; navigationState?: StoreNavigationState; resetScroll?: boolean } = {}) => {
      void navigate(
        { pathname: location.pathname, search: toQueryString(next) },
        { replace: options.replace, state: options.navigationState, preventScrollReset: !options.resetScroll },
      )
    },
    [navigate, location.pathname],
  )

  return {
    state,
    commit,
    isCanonical: canonicalSearch === location.search,
    navigationState: (location.state ?? {}) as StoreNavigationState,
  }
}
