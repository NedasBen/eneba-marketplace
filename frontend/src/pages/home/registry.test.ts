import { describe, expect, it } from 'vitest'
import { isHeroBlockData, isProductRailData } from './blockData'
import { blockComponentFor, blockComponents } from './registry'

describe('homepage block registry', () => {
  it('discovers a component for every file in ./blocks, keyed by file name', () => {
    expect(Object.keys(blockComponents).sort()).toEqual(['hero', 'product_rail'])
  })

  it('resolves known layouts and returns undefined for unknown ones', () => {
    expect(blockComponentFor('hero')).toBeTypeOf('function')
    expect(blockComponentFor('product_rail')).toBeTypeOf('function')
    expect(blockComponentFor('countdown')).toBeUndefined()
    // Object prototype keys must not resolve to anything.
    expect(blockComponentFor('toString')).toBeUndefined()
    expect(blockComponentFor('constructor')).toBeUndefined()
  })
})

describe('block data guards', () => {
  it('accepts well-formed rail data and rejects malformed or empty data', () => {
    expect(isProductRailData({ products: [{ id: 1 }], see_all: '?sort=popularity' })).toBe(true)
    expect(isProductRailData({ products: [{ id: 1 }], see_all: null })).toBe(true)
    expect(isProductRailData({ products: [], see_all: null })).toBe(false)
    expect(isProductRailData({ products: 'nope', see_all: null })).toBe(false)
    expect(isProductRailData(null)).toBe(false)
  })

  it('accepts hero data only with at least one slide that has an image', () => {
    expect(isHeroBlockData({ slides: [{ image_url: 'https://cdn.example/hero.jpg' }] })).toBe(true)
    expect(isHeroBlockData({ slides: [] })).toBe(false)
    expect(isHeroBlockData({ slides: [{ title: 'No image' }] })).toBe(false)
    expect(isHeroBlockData('slides')).toBe(false)
  })
})
