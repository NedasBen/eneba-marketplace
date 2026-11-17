/** Mirrors of the Laravel API responses (see docs/ARCHITECTURE.md). */

export interface Money {
  amount_cents: number
  currency: string
}

export interface ProductCard {
  id: number
  name: string
  slug: string
  game: { name: string; slug: string; cover_image_url: string }
  platform: { slug: string; name: string; activation_service: string }
  region: { slug: string; name: string }
  price_from: Money | null
  retail_price: Money
  discount_percent: number | null
  offers_count: number
  in_stock: boolean
}

export interface SearchQueryMeta {
  original: string
  corrected: string | null
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface ProductListResponse {
  data: ProductCard[]
  meta: PaginationMeta
  query: SearchQueryMeta | null
}

export type ProductSortValue = 'relevance' | 'popularity' | 'price_asc' | 'price_desc' | 'release_date' | 'name'

export interface ProductListParams {
  q?: string
  platform?: string[]
  region?: string[]
  genre?: string[]
  price_min?: number
  price_max?: number
  in_stock?: boolean
  sort?: ProductSortValue
  page?: number
  per_page?: number
}

export interface FacetOption {
  slug: string
  name: string
  count: number
}

export interface CatalogFilters {
  platforms: (FacetOption & { activation_service: string })[]
  regions: FacetOption[]
  genres: FacetOption[]
  price: { min: Money | null; max: Money | null }
  sorts: { value: ProductSortValue; label: string; requires_query: boolean }[]
  default_sort: ProductSortValue
  per_page: { options: number[]; default: number }
}

export interface GameSuggestion {
  name: string
  slug: string
  cover_image_url: string
  price_from: Money | null
  platforms: string[]
  product_slug: string | null
  in_stock: boolean
}

export interface SuggestResponse {
  data: GameSuggestion[]
  query: SearchQueryMeta | null
}
