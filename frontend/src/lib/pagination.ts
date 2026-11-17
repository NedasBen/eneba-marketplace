export type PageItem = number | 'gap'

/**
 * Page numbers to show: always the first and last page, a window around the
 * current page, and gaps where pages are skipped. 1 … 4 5 6 … 30
 */
export function paginationRange(current: number, last: number, siblings = 1): PageItem[] {
  if (last <= 1) return [1]

  const start = Math.max(2, current - siblings)
  const end = Math.min(last - 1, current + siblings)
  const items: PageItem[] = [1]

  // A gap of exactly one page is pointless: show the page instead.
  if (start === 3) items.push(2)
  else if (start > 3) items.push('gap')

  for (let page = start; page <= end; page++) items.push(page)

  if (end === last - 2) items.push(last - 1)
  else if (end < last - 2) items.push('gap')

  items.push(last)

  return items
}
