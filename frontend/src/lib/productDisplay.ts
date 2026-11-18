import type { ProductEdition } from './api/types'

export interface PlatformEditions {
  platform: ProductEdition['platform']
  editions: ProductEdition[]
  isCurrent: boolean
  inStock: boolean
}

/**
 * Editions grouped by platform (keeping the API's order), for a two-level
 * switcher: pick a platform, then a region.
 */
export function groupEditionsByPlatform(editions: ProductEdition[]): PlatformEditions[] {
  const groups = new Map<string, PlatformEditions>()

  for (const edition of editions) {
    const group = groups.get(edition.platform.slug) ?? { platform: edition.platform, editions: [], isCurrent: false, inStock: false }
    group.editions.push(edition)
    group.isCurrent ||= edition.is_current
    group.inStock ||= edition.in_stock
    groups.set(edition.platform.slug, group)
  }

  return [...groups.values()]
}

/**
 * Eneba shows seller ratings out of 10 with a word ("9.80 Superb rating").
 * Our sellers store positive feedback in percent.
 */
export function sellerRating(positivePercent: number): { score: string; label: string } {
  const score = Math.max(0, Math.min(10, positivePercent / 10))
  const label = score >= 9.7 ? 'Superb' : score >= 9.4 ? 'Excellent' : score >= 9 ? 'Very good' : 'Good'

  return { score: score.toFixed(2), label }
}
