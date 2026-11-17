import { useEffect, useId, useState } from 'react'
import { Link } from 'react-router'
import { Container } from '../../../components/layout/Container'
import { ButtonLink } from '../../../components/ui/Button'
import type { HeroSlide } from '../../../lib/api/types'
import { cn } from '../../../lib/cn'
import { formatMoney } from '../../../lib/format'
import { isHeroBlockData } from '../blockData'
import type { BlockComponentProps } from '../registry'

const AUTOPLAY_MS = 6000
const SIDE_TILES = 3

const prefersReducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

const slideHref = (slide: HeroSlide) => (slide.product_slug ? `/product/${slide.product_slug}` : '/store')

function ArrowButton({ direction, onClick }: { direction: 'previous' | 'next'; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={direction === 'previous' ? 'Previous slide' : 'Next slide'}
      className={cn(
        'absolute top-1/2 z-10 grid size-9 -translate-y-1/2 place-items-center bg-black/45 text-white opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:bg-black/70',
        direction === 'previous' ? 'left-2' : 'right-2',
      )}
    >
      <svg aria-hidden viewBox="0 0 24 24" className="size-[22px]" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round">
        <path d={direction === 'previous' ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6'} />
      </svg>
    </button>
  )
}

/**
 * Hero carousel (layout "hero"), modelled on eneba.com: a wide main slide
 * with its own call to action, and portrait tiles for the other promotions
 * beside it on desktop. Rotates every 6 s; pauses on hover, on keyboard
 * focus, via the pause button, and never auto-rotates for users who prefer
 * reduced motion (WAI-ARIA carousel pattern).
 */
export default function HeroBlock({ block }: BlockComponentProps) {
  const [current, setCurrent] = useState(0)
  const [isHovered, setIsHovered] = useState(false)
  const [hasFocus, setHasFocus] = useState(false)
  const [isStopped, setIsStopped] = useState(prefersReducedMotion)
  const slidesId = useId()

  const slides = isHeroBlockData(block.data) ? block.data.slides : []
  const count = slides.length
  const isRotating = count > 1 && !isStopped && !isHovered && !hasFocus

  useEffect(() => {
    if (!isRotating) return
    const timer = window.setInterval(() => setCurrent((index) => (index + 1) % count), AUTOPLAY_MS)
    return () => window.clearInterval(timer)
  }, [isRotating, count])

  if (count === 0) return null

  const go = (index: number) => setCurrent((index + count) % count)
  const tiles = Array.from({ length: Math.min(SIDE_TILES, count - 1) }, (_, offset) => slides[(current + offset + 1) % count])

  return (
    <Container className="pt-5">
      <section
        aria-roledescription="carousel"
        aria-label={block.title}
        className="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto]"
        onFocus={() => setHasFocus(true)}
        onBlur={(event) => {
          if (!event.currentTarget.contains(event.relatedTarget)) setHasFocus(false)
        }}
      >
        <div className="group relative overflow-hidden bg-card" onMouseEnter={() => setIsHovered(true)} onMouseLeave={() => setIsHovered(false)}>
          <div id={slidesId} aria-live={isRotating ? 'off' : 'polite'} className="relative aspect-[16/9] sm:aspect-[21/9] md:h-[330px] md:aspect-auto">
            {slides.map((slide, index) => (
              <div
                key={slide.id}
                role="group"
                aria-roledescription="slide"
                aria-label={`${index + 1} of ${count}: ${slide.title}`}
                aria-hidden={index !== current}
                inert={index !== current}
                className={cn('absolute inset-0 transition-opacity duration-500', index === current ? 'opacity-100' : 'opacity-0')}
              >
                <img
                  src={slide.image_url}
                  alt=""
                  loading={index === 0 ? 'eager' : 'lazy'}
                  className="absolute inset-0 size-full object-cover"
                />
                <div aria-hidden className="absolute inset-0 bg-linear-to-r from-black/80 via-black/40 to-transparent" />
                <div className="relative flex h-full max-w-[560px] flex-col justify-end gap-2 p-5 md:justify-center md:p-9">
                  <h2 className="text-2xl leading-tight font-extrabold md:text-4xl">{slide.title}</h2>
                  {slide.subtitle && <p className="line-clamp-2 hidden text-sm text-soft sm:block">{slide.subtitle}</p>}
                  <div className="mt-1 flex flex-wrap items-center gap-4">
                    <ButtonLink to={slideHref(slide)} className="min-w-[160px] uppercase">
                      {slide.cta_label}
                    </ButtonLink>
                    {slide.price_from && (
                      <p className="text-sm font-extrabold">
                        <span className="text-muted">From </span>
                        <span className="text-lg">{formatMoney(slide.price_from)}</span>
                      </p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {count > 1 && (
            <>
              <ArrowButton direction="previous" onClick={() => go(current - 1)} />
              <ArrowButton direction="next" onClick={() => go(current + 1)} />
              {/* Top-right on small screens, where the slide's text and price sit at the bottom. */}
              <div className="absolute top-3 right-3 z-10 flex items-center gap-2 md:top-auto md:bottom-3">
                <button
                  type="button"
                  onClick={() => setIsStopped((stopped) => !stopped)}
                  aria-label={isStopped ? 'Start automatic slide show' : 'Stop automatic slide show'}
                  className="grid size-6 place-items-center bg-black/45 text-white hover:bg-black/70"
                >
                  <svg aria-hidden viewBox="0 0 24 24" className="size-[14px]" fill="currentColor">
                    {isStopped ? <path d="M8 5v14l11-7z" /> : <path d="M7 5h4v14H7zM13 5h4v14h-4z" />}
                  </svg>
                </button>
                <div role="group" aria-label="Choose slide" className="flex gap-1">
                  {slides.map((slide, index) => (
                    <button
                      key={slide.id}
                      type="button"
                      onClick={() => go(index)}
                      aria-label={`Show slide ${index + 1}: ${slide.title}`}
                      aria-current={index === current}
                      aria-controls={slidesId}
                      className={cn('h-[4px] w-5 transition-colors', index === current ? 'bg-accent' : 'bg-white/45 hover:bg-white/80')}
                    />
                  ))}
                </div>
              </div>
            </>
          )}
        </div>

        {tiles.length > 0 && (
          <ul aria-label="More featured games" className="hidden gap-2 md:flex">
            {tiles.map((slide) => (
              <li key={slide.id}>
                <Link
                  to={slideHref(slide)}
                  className="group/tile relative block h-[330px] w-[150px] overflow-hidden bg-card lg:w-[165px]"
                  aria-label={slide.price_from ? `${slide.title}, from ${formatMoney(slide.price_from)}` : slide.title}
                >
                  <img
                    src={slide.cover_image_url ?? slide.image_url}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover transition-opacity duration-150 group-hover/tile:opacity-80"
                  />
                  <span className="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/85 to-transparent p-2 pt-7">
                    <span className="line-clamp-2 text-xs leading-snug font-extrabold">{slide.title}</span>
                    {slide.price_from && <span className="mt-1 block text-sm font-extrabold text-accent">{formatMoney(slide.price_from)}</span>}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </Container>
  )
}
