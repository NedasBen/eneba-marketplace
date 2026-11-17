import type { ProductListParams, ProductSortValue } from './api/types'

/**
 * Store filter state. The URL is the single source of truth; this is its
 * parsed, validated form.
 */
export interface StoreState {
  q?: string
  platform: string[]
  region: string[]
  genre: string[]
  /** EUR, as typed by the user (the API converts to cents). */
  priceMin?: number
  priceMax?: number
  inStock: boolean
  /** Undefined = the API default (relevance with q, popularity without). */
  sort?: ProductSortValue
  page: number
}

export type FacetKey = 'platform' | 'region' | 'genre'

export const FACET_KEYS: FacetKey[] = ['platform', 'region', 'genre']

const SORTS: ProductSortValue[] = ['relevance', 'popularity', 'price_asc', 'price_desc', 'discount', 'release_date', 'name']
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/
const MAX_FACET_VALUES = 20
const MAX_QUERY_LENGTH = 100
const MAX_PRICE = 100_000

export const EMPTY_STORE_STATE: StoreState = { platform: [], region: [], genre: [], inStock: true, page: 1 }

function parseList(params: URLSearchParams, key: string): string[] {
  // Accept both our comma form and the array form older links may use.
  const raw = [...params.getAll(key), ...params.getAll(`${key}[]`)].flatMap((value) => value.split(','))
  const slugs = raw.map((value) => value.trim().toLowerCase()).filter((value) => SLUG.test(value))

  return [...new Set(slugs)].slice(0, MAX_FACET_VALUES)
}

function parsePrice(value: string | null): number | undefined {
  if (value === null || value.trim() === '') return undefined
  const number = Number(value)

  return Number.isFinite(number) && number >= 0 && number <= MAX_PRICE ? Math.round(number * 100) / 100 : undefined
}

/**
 * Parse and sanitise store params. Malformed values (page=abc, unknown sort,
 * negative prices, non-slug characters) are dropped here, before any request.
 */
export function parseStoreParams(params: URLSearchParams): StoreState {
  const q = (params.get('q') ?? '').trim().slice(0, MAX_QUERY_LENGTH) || undefined

  let priceMin = parsePrice(params.get('price_min'))
  let priceMax = parsePrice(params.get('price_max'))
  if (priceMin !== undefined && priceMax !== undefined && priceMax < priceMin) {
    priceMax = undefined
  }
  if (priceMin === 0) priceMin = undefined

  const sortParam = params.get('sort') as ProductSortValue | null
  const sort = sortParam !== null && SORTS.includes(sortParam) && !(sortParam === 'relevance' && q === undefined) ? sortParam : undefined

  const pageNumber = Number(params.get('page'))
  const inStockParam = params.get('in_stock')

  return {
    q,
    platform: parseList(params, 'platform'),
    region: parseList(params, 'region'),
    genre: parseList(params, 'genre'),
    priceMin,
    priceMax,
    inStock: !(inStockParam === '0' || inStockParam === 'false'),
    sort,
    page: Number.isInteger(pageNumber) && pageNumber > 1 ? pageNumber : 1,
  }
}

/**
 * Canonical query string: comma lists, defaults omitted, fixed key order,
 * commas left readable (`?platform=pc,xbox`), so links stay short and stable.
 */
export function toQueryString(state: StoreState): string {
  const parts: string[] = []
  const add = (key: string, value: string) => parts.push(`${key}=${value}`)

  if (state.q) add('q', encodeURIComponent(state.q))
  for (const key of FACET_KEYS) {
    if (state[key].length > 0) add(key, state[key].map(encodeURIComponent).join(','))
  }
  if (state.priceMin !== undefined) add('price_min', String(state.priceMin))
  if (state.priceMax !== undefined) add('price_max', String(state.priceMax))
  if (!state.inStock) add('in_stock', '0')
  if (state.sort) add('sort', state.sort)
  if (state.page > 1) add('page', String(state.page))

  return parts.length === 0 ? '' : `?${parts.join('&')}`
}

/** Params for GET /products. */
export function toProductListParams(state: StoreState, perPage: number): ProductListParams {
  return {
    q: state.q,
    platform: state.platform,
    region: state.region,
    genre: state.genre,
    price_min: state.priceMin,
    price_max: state.priceMax,
    in_stock: state.inStock,
    sort: state.sort,
    page: state.page,
    per_page: perPage,
  }
}

/** Params for GET /catalog/filters: same filters, no sort or paging. */
export function toFilterParams(state: StoreState): Omit<ProductListParams, 'sort' | 'page' | 'per_page'> {
  return {
    q: state.q,
    platform: state.platform,
    region: state.region,
    genre: state.genre,
    price_min: state.priceMin,
    price_max: state.priceMax,
    in_stock: state.inStock,
  }
}

/** A filter change; every filter change starts again from page 1. */
export function withFilters(state: StoreState, patch: Partial<Omit<StoreState, 'page'>>): StoreState {
  return { ...state, ...patch, page: 1 }
}

export function toggleFacetValue(state: StoreState, facet: FacetKey, slug: string): StoreState {
  const values = state[facet].includes(slug) ? state[facet].filter((value) => value !== slug) : [...state[facet], slug]

  return withFilters(state, { [facet]: values })
}

export function activeFilterCount(state: StoreState): number {
  return (
    state.platform.length +
    state.region.length +
    state.genre.length +
    (state.priceMin !== undefined || state.priceMax !== undefined ? 1 : 0) +
    (state.inStock ? 0 : 1)
  )
}

/** Clears filters but keeps the search query and sort. */
export function clearFilters(state: StoreState): StoreState {
  return { ...EMPTY_STORE_STATE, q: state.q, sort: state.sort }
}

/**
 * Recover from a 422 caused by params the API rejects (typically an old link
 * to a platform/region/genre that no longer exists). Removes exactly the
 * offending values, e.g. `platform.1` drops only the second platform.
 * Returns null when no error maps to a removable param (avoids retry loops).
 */
export function withoutInvalidParams(state: StoreState, errors: Record<string, string[]>): StoreState | null {
  const next: StoreState = { ...state, platform: [...state.platform], region: [...state.region], genre: [...state.genre] }
  const removeIndexes: Record<FacetKey, Set<number>> = { platform: new Set(), region: new Set(), genre: new Set() }
  let changed = false

  for (const key of Object.keys(errors)) {
    const [field, index] = key.split('.')

    if ((FACET_KEYS as string[]).includes(field)) {
      const facet = field as FacetKey
      if (index !== undefined && /^\d+$/.test(index) && Number(index) < next[facet].length) {
        removeIndexes[facet].add(Number(index))
        changed = true
      } else if (next[facet].length > 0) {
        next[facet] = []
        changed = true
      }
      continue
    }

    switch (field) {
      case 'q':
        changed ||= next.q !== undefined
        next.q = undefined
        break
      case 'price_min':
        changed ||= next.priceMin !== undefined
        next.priceMin = undefined
        break
      case 'price_max':
        changed ||= next.priceMax !== undefined
        next.priceMax = undefined
        break
      case 'in_stock':
        changed ||= !next.inStock
        next.inStock = true
        break
      case 'sort':
        changed ||= next.sort !== undefined
        next.sort = undefined
        break
      case 'page':
      case 'per_page':
        changed ||= next.page !== 1
        next.page = 1
        break
    }
  }

  for (const facet of FACET_KEYS) {
    if (removeIndexes[facet].size > 0) {
      next[facet] = next[facet].filter((_, index) => !removeIndexes[facet].has(index))
    }
  }

  return changed ? { ...next, page: 1 } : null
}
