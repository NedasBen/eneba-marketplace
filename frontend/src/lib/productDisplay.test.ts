import { describe, expect, it } from 'vitest'
import type { ProductEdition } from './api/types'
import { groupEditionsByPlatform, sellerRating } from './productDisplay'

const edition = (platform: string, region: string, overrides: Partial<ProductEdition> = {}): ProductEdition => ({
  slug: `${platform}-${region}`,
  name: `${platform} ${region}`,
  platform: { slug: platform, name: platform.toUpperCase(), activation_service: 'Service' },
  region: { slug: region, name: region.toUpperCase() },
  price_from: { amount_cents: 1000, currency: 'EUR' },
  in_stock: true,
  is_current: false,
  ...overrides,
})

describe('groupEditionsByPlatform', () => {
  it('groups editions by platform in API order and marks the current and in-stock groups', () => {
    const groups = groupEditionsByPlatform([
      edition('pc', 'global'),
      edition('pc', 'europe', { is_current: true }),
      edition('xbox', 'europe', { in_stock: false, price_from: null }),
    ])

    expect(groups.map((group) => [group.platform.slug, group.editions.map((e) => e.region.slug), group.isCurrent, group.inStock])).toEqual([
      ['pc', ['global', 'europe'], true, true],
      ['xbox', ['europe'], false, false],
    ])
  })
})

describe('sellerRating', () => {
  it.each([
    [99.8, '9.98', 'Superb'],
    [96.5, '9.65', 'Excellent'],
    [91.7, '9.17', 'Very good'],
    [88.3, '8.83', 'Good'],
  ])('%f%% positive is %s %s', (percent, score, label) => {
    expect(sellerRating(percent)).toEqual({ score, label })
  })
})
