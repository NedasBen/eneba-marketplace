import { keepPreviousData, queryOptions, useQuery } from '@tanstack/react-query'
import { apiGet } from './client'
import type { CatalogFilters, HomepageBlock, OfferState, ProductDetail, ProductListParams, ProductListResponse, SuggestResponse } from './types'

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

  product: (slug: string) =>
    queryOptions({
      queryKey: ['product', slug],
      queryFn: ({ signal }) => apiGet<{ data: ProductDetail }>(`/products/${encodeURIComponent(slug)}`, undefined, signal),
      select: (response) => response.data,
      // Switching edition keeps the previous page on screen (dimmed) until the next one loads.
      placeholderData: keepPreviousData,
    }),

  offerStates: (ids: number[]) =>
    queryOptions({
      queryKey: ['offer-states', [...ids].sort((a, b) => a - b)],
      queryFn: ({ signal }) => apiGet<{ data: OfferState[] }>('/offers', { ids: ids.map(String) }, signal),
      select: (response) => response.data,
      enabled: ids.length > 0,
      staleTime: 15_000,
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

export function useProduct(slug: string) {
  return useQuery(catalogQueries.product(slug))
}

export function useOfferStates(ids: number[]) {
  return useQuery(catalogQueries.offerStates(ids))
}

export function useHomepage() {
  return useQuery(catalogQueries.homepage())
}

export function useSearchSuggestions(query: string) {
  return useQuery(catalogQueries.suggestions(query.trim()))
}
