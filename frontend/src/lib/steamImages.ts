/**
 * Steam serves portrait covers at 300×450 (`library_600x900.jpg`, ~65 KB)
 * and 600×900 (`library_600x900_2x.jpg`, ~200 KB). The catalog stores the
 * 2x URL; cards are ~150–220 CSS px wide, so most screens only need the 1x
 * file. Other URLs (header-image fallbacks) are returned unchanged.
 */
const COVER_2X = /library_600x900_2x\.jpg$/

/**
 * `sizes` for product cards: ~220px on desktop, a third of the width on
 * tablets, and half the width minus the page gutter on phones (two columns),
 * so a 390px phone at 1.75× still fits the 300px file.
 */
export const CARD_IMAGE_SIZES = '(min-width: 1200px) 220px, (min-width: 600px) 30vw, calc(50vw - 28px)'

/**
 * Steam screenshots come as `….1920x1080.jpg` (~850 KB) and `….600x338.jpg`
 * (~100 KB). Thumbnails use the small one; the lightbox keeps the full size.
 */
const SCREENSHOT_FULL = /\.1920x1080\.jpg/

export function screenshotThumbnailProps(url: string): { src: string; srcSet?: string } {
  if (!SCREENSHOT_FULL.test(url)) return { src: url }

  const small = url.replace(SCREENSHOT_FULL, '.600x338.jpg')
  return { src: small, srcSet: `${small} 600w, ${url} 1920w` }
}

export function coverImageProps(url: string): { src: string; srcSet?: string } {
  if (!COVER_2X.test(url)) return { src: url }

  const small = url.replace(COVER_2X, 'library_600x900.jpg')
  return { src: small, srcSet: `${small} 300w, ${url} 600w` }
}
