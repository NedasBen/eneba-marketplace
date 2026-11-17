import { Link } from 'react-router'
import { cn } from '../../lib/cn'
import { paginationRange } from '../../lib/pagination'

interface PaginationProps {
  page: number
  lastPage: number
  /** Builds the link for a page, so every page is a real, shareable URL. */
  hrefFor: (page: number) => string
}

const itemClasses = 'grid min-h-7 min-w-7 place-items-center px-2 text-sm font-extrabold transition-colors'

export function Pagination({ page, lastPage, hrefFor }: PaginationProps) {
  if (lastPage <= 1) return null

  return (
    <nav aria-label="Pagination" className="flex justify-center">
      <ul className="flex flex-wrap items-center gap-1">
        <li>
          {page > 1 ? (
            <Link to={hrefFor(page - 1)} className={cn(itemClasses, 'bg-white/10 hover:bg-white/40')} aria-label="Previous page">
              ‹
            </Link>
          ) : (
            <span className={cn(itemClasses, 'bg-white/5 text-white/30')} aria-hidden>
              ‹
            </span>
          )}
        </li>
        {paginationRange(page, lastPage).map((item, index) =>
          item === 'gap' ? (
            <li key={`gap-${index}`} aria-hidden className="px-1 text-muted">
              …
            </li>
          ) : (
            <li key={item}>
              <Link
                to={hrefFor(item)}
                aria-current={item === page ? 'page' : undefined}
                aria-label={`Page ${item}`}
                className={cn(itemClasses, item === page ? 'bg-accent text-black' : 'bg-white/10 hover:bg-white/40')}
              >
                {item}
              </Link>
            </li>
          ),
        )}
        <li>
          {page < lastPage ? (
            <Link to={hrefFor(page + 1)} className={cn(itemClasses, 'bg-white/10 hover:bg-white/40')} aria-label="Next page">
              ›
            </Link>
          ) : (
            <span className={cn(itemClasses, 'bg-white/5 text-white/30')} aria-hidden>
              ›
            </span>
          )}
        </li>
      </ul>
    </nav>
  )
}
