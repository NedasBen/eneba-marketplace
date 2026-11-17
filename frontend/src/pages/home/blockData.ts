import type { HeroBlockData, ProductRailData } from '../../lib/api/types'

/**
 * Runtime guards for block data. The API is the source of the shape, but a
 * block with unexpected data must render nothing rather than crash the page.
 */
const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null

export function isHeroBlockData(data: unknown): data is HeroBlockData {
  return isRecord(data) && Array.isArray(data.slides) && data.slides.length > 0 && data.slides.every((slide) => isRecord(slide) && typeof slide.image_url === 'string')
}

export function isProductRailData(data: unknown): data is ProductRailData {
  return isRecord(data) && Array.isArray(data.products) && data.products.length > 0 && (data.see_all === null || typeof data.see_all === 'string')
}
