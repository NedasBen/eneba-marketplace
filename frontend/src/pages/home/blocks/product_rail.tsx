import { useCallback, useId, useRef, useState } from 'react'
import { Container } from '../../../components/layout/Container'
import { ButtonLink } from '../../../components/ui/Button'
import { ProductCard } from '../../../components/ui/ProductCard'
import { cn } from '../../../lib/cn'
import { isProductRailData } from '../blockData'
import type { BlockComponentProps } from '../registry'

function ScrollButton({ direction, disabled, onClick }: { direction: 'previous' | 'next'; disabled: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={direction === 'previous' ? 'Scroll left' : 'Scroll right'}
      className="grid size-8 place-items-center bg-white/10 text-white transition-colors hover:bg-white/40 disabled:opacity-30 disabled:hover:bg-white/10"
    >
      <svg aria-hidden viewBox="0 0 24 24" className="size-[18px]" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round">
        <path d={direction === 'previous' ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6'} />
      </svg>
    </button>
  )
}

/**
 * A titled, horizontally scrolling row of product cards (layout
 * "product_rail"), on alternating full-width bands like eneba.com.
 * Scrolls by touch, trackpad, the arrow buttons, or the keyboard once the
 * row has focus; "See all" opens the store with the block's own query.
 */
export default function ProductRailBlock({ block, index }: BlockComponentProps) {
  const scrollerRef = useRef<HTMLUListElement>(null)
  const [edges, setEdges] = useState({ atStart: true, atEnd: false })
  const headingId = useId()

  const updateEdges = useCallback(() => {
    const scroller = scrollerRef.current
    if (scroller === null) return
    setEdges({
      atStart: scroller.scrollLeft <= 1,
      atEnd: scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 1,
    })
  }, [])

  // Measure once the list is in the DOM (callback ref, so no effect is needed).
  const attachScroller = useCallback(
    (node: HTMLUListElement | null) => {
      scrollerRef.current = node
      if (node !== null) updateEdges()
    },
    [updateEdges],
  )

  if (!isProductRailData(block.data)) return null
  const { products, see_all: seeAll } = block.data

  const scrollByPage = (direction: 1 | -1) => {
    const scroller = scrollerRef.current
    scroller?.scrollBy({ left: direction * scroller.clientWidth * 0.9, behavior: 'smooth' })
  }

  return (
    <section aria-labelledby={headingId} className={cn('py-7', index % 2 === 0 ? 'bg-band' : 'bg-page')}>
      <Container className="flex flex-col gap-4">
        <div className="flex items-end justify-between gap-4">
          <h2 id={headingId} className="text-2xl font-extrabold">
            {block.title}
          </h2>
          <div className="hidden gap-1 sm:flex">
            <ScrollButton direction="previous" disabled={edges.atStart} onClick={() => scrollByPage(-1)} />
            <ScrollButton direction="next" disabled={edges.atEnd} onClick={() => scrollByPage(1)} />
          </div>
        </div>

        <ul
          ref={attachScroller}
          onScroll={updateEdges}
          tabIndex={0}
          aria-label={`${block.title}, scrollable list`}
          className="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 outline-offset-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {products.map((product) => (
            <li key={product.id} className="grid w-[46%] shrink-0 snap-start xs:w-[175px] md:w-[190px]">
              <ProductCard product={product} />
            </li>
          ))}
        </ul>

        {seeAll !== null && (
          <div className="flex justify-center pt-1">
            <ButtonLink to={`/store${seeAll}`} variant="outline" size="sm" className="min-w-[150px]">
              See all
            </ButtonLink>
          </div>
        )}
      </Container>
    </section>
  )
}
