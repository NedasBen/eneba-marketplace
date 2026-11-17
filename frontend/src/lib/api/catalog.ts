import { keepPreviousData, queryOptions, useQuery } from '@tanstack/react-query'
import { apiGet } from './client'
import type { CatalogFilters, HomepageBlock, ProductListParams, ProductListResponse, SuggestResponse } from './types'

export const SUGGEST_MIN_LENGTH = 2

export const catalogQueries = {
  products: (params: ProductListParams) =>
    queryOptions({
      queryKey: ['products', params],
      queryFn: ({ signal }) => apiGet<ProductListResponse>('/products', { ...params }, signal),
      placeholderData: keepPreviousData,
    }),

  filters: (params: Omit<ProductListParams, 'sort' | 'page' | 'per_page'> = {}) =>
    queryOptions({
      queryKey: ['catalog-filters', params],
      queryFn: ({ signal }) => apiGet<{ data: CatalogFilters }>('/catalog/filters', { ...params }, signal),
      select: (response) => response.data,
      staleTime: 5 * 60_000,
    }),

  homepage: () =>
    queryOptions({
      queryKey: ['homepage'],
      queryFn: ({ signal }) => apiGet<{ data: HomepageBlock[] }>('/homepage', undefined, signal),
      select: (response) => response.data,
      staleTime: 60_000,
    }),

  suggestions: (query: string) =>
    queryOptions({
      queryKey: ['search-suggest', query],
      queryFn: ({ signal }) => apiGet<SuggestResponse>('/search/suggest', { q: query }, signal),
      enabled: query.trim().length >= SUGGEST_MIN_LENGTH,
      // Keep the last results on screen while the next keystroke loads.
      placeholderData: keepPreviousData,
      staleTime: 60_000,
    }),
}

export function useProducts(params: ProductListParams) {
  return useQuery(catalogQueries.products(params))
}

export function useCatalogFilters(params?: Omit<ProductListParams, 'sort' | 'page' | 'per_page'>) {
  return useQuery(catalogQueries.filters(params))
}

export function useHomepage() {
  return useQuery(catalogQueries.homepage())
}

export function useSearchSuggestions(query: string) {
  return useQuery(catalogQueries.suggestions(query.trim()))
}
