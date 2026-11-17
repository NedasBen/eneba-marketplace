import { describe, expect, it } from 'vitest'
import {
  EMPTY_STORE_STATE,
  activeFilterCount,
  clearFilters,
  parseStoreParams,
  toQueryString,
  toggleFacetValue,
  withFilters,
  withoutInvalidParams,
  type StoreState,
} from './storeParams'

const parse = (query: string) => parseStoreParams(new URLSearchParams(query))

describe('parseStoreParams', () => {
  it('reads comma lists and the array form older links use', () => {
    expect(parse('platform=pc,xbox&region[]=europe&region[]=global').platform).toEqual(['pc', 'xbox'])
    expect(parse('platform=pc,xbox&region[]=europe&region[]=global').region).toEqual(['europe', 'global'])
  })

  it('drops malformed slugs, duplicates and empty items', () => {
    expect(parse('genre=rpg,,RPG,act%20ion,<script>,indie').genre).toEqual(['rpg', 'indie'])
  })

  it('caps a facet at 20 values', () => {
    const many = Array.from({ length: 25 }, (_, index) => `genre-${index}`).join(',')

    expect(parse(`genre=${many}`).genre).toHaveLength(20)
  })

  it('accepts decimal euro prices and rejects nonsense', () => {
    expect(parse('price_min=9.99&price_max=30').priceMin).toBe(9.99)
    expect(parse('price_min=-5').priceMin).toBeUndefined()
    expect(parse('price_max=abc').priceMax).toBeUndefined()
    expect(parse('price_max=999999').priceMax).toBeUndefined()
  })

  it('drops a maximum below the minimum and a zero minimum', () => {
    expect(parse('price_min=30&price_max=10')).toMatchObject({ priceMin: 30, priceMax: undefined })
    expect(parse('price_min=0&price_max=10')).toMatchObject({ priceMin: undefined, priceMax: 10 })
  })

  it('accepts known sorts and drops relevance without a query', () => {
    expect(parse('sort=price_asc').sort).toBe('price_asc')
    expect(parse('sort=cheapest').sort).toBeUndefined()
    expect(parse('sort=relevance').sort).toBeUndefined()
    expect(parse('q=witcher&sort=relevance').sort).toBe('relevance')
  })

  it('treats bad page numbers as page 1', () => {
    expect(parse('page=3').page).toBe(3)
    expect(parse('page=0').page).toBe(1)
    expect(parse('page=2.5').page).toBe(1)
    expect(parse('page=abc').page).toBe(1)
  })

  it('reads in_stock=0 or false as including sold-out products', () => {
    expect(parse('in_stock=0').inStock).toBe(false)
    expect(parse('in_stock=false').inStock).toBe(false)
    expect(parse('').inStock).toBe(true)
  })

  it('trims the query and ignores a blank one', () => {
    expect(parse('q=%20%20witcher%20').q).toBe('witcher')
    expect(parse('q=%20%20').q).toBeUndefined()
  })
})

describe('toQueryString', () => {
  it('writes readable comma lists in a fixed order and omits defaults', () => {
    const state: StoreState = {
      ...EMPTY_STORE_STATE,
      q: 'red dead',
      platform: ['pc', 'xbox'],
      genre: ['rpg'],
      priceMax: 30,
      inStock: false,
      sort: 'price_asc',
      page: 2,
    }

    expect(toQueryString(state)).toBe('?q=red%20dead&platform=pc,xbox&genre=rpg&price_max=30&in_stock=0&sort=price_asc&page=2')
    expect(toQueryString(EMPTY_STORE_STATE)).toBe('')
  })

  it('round-trips through parsing', () => {
    const query = '?q=gta%205&platform=playstation&region=europe,global&price_min=5&sort=name&page=3'

    expect(toQueryString(parse(query.slice(1)))).toBe(query)
  })
})

describe('filter changes', () => {
  it('resets to page 1 whenever a filter changes', () => {
    const onPageFour = { ...EMPTY_STORE_STATE, page: 4 }

    expect(withFilters(onPageFour, { inStock: false }).page).toBe(1)
    expect(toggleFacetValue(onPageFour, 'platform', 'pc')).toMatchObject({ platform: ['pc'], page: 1 })
  })

  it('toggles a facet value off again', () => {
    expect(toggleFacetValue({ ...EMPTY_STORE_STATE, region: ['europe', 'global'] }, 'region', 'europe').region).toEqual(['global'])
  })

  it('counts and clears filters but keeps the query and sort', () => {
    const state: StoreState = { ...EMPTY_STORE_STATE, q: 'witcher', sort: 'name', platform: ['pc', 'xbox'], priceMin: 5, inStock: false }

    expect(activeFilterCount(state)).toBe(4)
    expect(clearFilters(state)).toEqual({ ...EMPTY_STORE_STATE, q: 'witcher', sort: 'name' })
  })
})

describe('withoutInvalidParams', () => {
  const state: StoreState = { ...EMPTY_STORE_STATE, platform: ['pc', 'dreamcast', 'xbox'], genre: ['rpg'], priceMin: 30, priceMax: 10, page: 3 }

  it('removes only the rejected facet values and returns to page 1', () => {
    expect(withoutInvalidParams(state, { 'platform.1': ['The selected platform.1 is invalid.'] })).toMatchObject({
      platform: ['pc', 'xbox'],
      genre: ['rpg'],
      page: 1,
    })
  })

  it('removes several rejected values across facets at once', () => {
    expect(withoutInvalidParams(state, { 'platform.0': ['x'], 'platform.2': ['x'], 'genre.0': ['x'] })).toMatchObject({
      platform: ['dreamcast'],
      genre: [],
    })
  })

  it('drops a rejected price bound', () => {
    expect(withoutInvalidParams(state, { price_max: ['must be greater'] })).toMatchObject({ priceMin: 30, priceMax: undefined })
  })

  it('clears a whole facet when the error is about the list itself', () => {
    expect(withoutInvalidParams(state, { platform: ['too many'] })?.platform).toEqual([])
  })

  it('returns null when nothing removable caused the error, so the UI stops retrying', () => {
    expect(withoutInvalidParams(state, { something_else: ['x'] })).toBeNull()
    expect(withoutInvalidParams(EMPTY_STORE_STATE, { 'platform.0': ['x'] })).toBeNull()
  })
})
