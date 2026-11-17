import { useId, useRef, useState, type KeyboardEvent } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router'
import { SUGGEST_MIN_LENGTH, useSearchSuggestions } from '../../lib/api/catalog'
import type { GameSuggestion } from '../../lib/api/types'
import { cn } from '../../lib/cn'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { Badge } from '../ui/Badge'
import { buttonClasses } from '../ui/buttonStyles'
import { PriceTag } from '../ui/PriceTag'
import { Skeleton } from '../ui/Skeleton'

const DEBOUNCE_MS = 200

const PLATFORM_LABELS: Record<string, string> = { pc: 'PC', playstation: 'PlayStation', xbox: 'Xbox', 'nintendo-switch': 'Switch' }

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg aria-hidden viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round">
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  )
}

/**
 * Header search with autocomplete, implemented as an ARIA 1.2 combobox:
 * the input keeps focus while ↑/↓ move a virtual highlight
 * (aria-activedescendant), Enter opens the highlighted game or the full
 * results page, Escape closes. While open, the page behind is dimmed like
 * on eneba.com.
 */
export function SearchBox() {
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const listboxId = useId()
  const inputRef = useRef<HTMLInputElement>(null)

  const urlQuery = location.pathname === '/store' ? (searchParams.get('q') ?? '') : ''
  const [query, setQuery] = useState(urlQuery)
  const [isOpen, setIsOpen] = useState(false)
  const [highlightedIndex, setHighlightedIndex] = useState(-1)

  // Mirror the URL when navigating between store searches (adjusting state
  // during render, not in an effect, avoids an extra render pass).
  const [syncedUrlQuery, setSyncedUrlQuery] = useState(urlQuery)
  if (urlQuery !== syncedUrlQuery) {
    setSyncedUrlQuery(urlQuery)
    setQuery(urlQuery)
  }

  const trimmed = query.trim()
  const debouncedQuery = useDebouncedValue(trimmed, DEBOUNCE_MS)
  const suggestions = useSearchSuggestions(debouncedQuery)
  const games: GameSuggestion[] = trimmed.length >= SUGGEST_MIN_LENGTH ? (suggestions.data?.data ?? []) : []
  const correction = trimmed.length >= SUGGEST_MIN_LENGTH ? suggestions.data?.query?.corrected : null

  const showPanel = isOpen && trimmed.length >= SUGGEST_MIN_LENGTH
  // The last option is always "Show all results".
  const optionCount = games.length + 1
  const optionId = (index: number) => `${listboxId}-option-${index}`
  // New results can be shorter than the old ones; never point past the end.
  const activeIndex = highlightedIndex < optionCount ? highlightedIndex : -1
  const setActiveIndex = setHighlightedIndex

  const close = () => {
    setIsOpen(false)
    setActiveIndex(-1)
  }

  const goToResults = () => {
    if (trimmed === '') return
    close()
    inputRef.current?.blur()
    void navigate(`/store?q=${encodeURIComponent(trimmed)}`)
  }

  const goToGame = (game: GameSuggestion) => {
    close()
    inputRef.current?.blur()
    void navigate(game.product_slug ? `/product/${game.product_slug}` : `/store?q=${encodeURIComponent(game.name)}`)
  }

  const choose = (index: number) => {
    if (index >= 0 && index < games.length) goToGame(games[index])
    else goToResults()
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        setIsOpen(true)
        setActiveIndex((activeIndex + 1) % optionCount)
        break
      case 'ArrowUp':
        event.preventDefault()
        setIsOpen(true)
        setActiveIndex(activeIndex <= 0 ? optionCount - 1 : activeIndex - 1)
        break
      case 'Enter':
        event.preventDefault()
        choose(showPanel ? activeIndex : -1)
        break
      case 'Escape':
        if (showPanel) {
          event.preventDefault()
          close()
        }
        break
      case 'Tab':
        close()
        break
    }
  }

  return (
    <>
      {showPanel && <div aria-hidden className="fixed inset-0 z-40 bg-black/55" onMouseDown={close} />}

      <div className={cn('relative w-full', showPanel && 'z-50')}>
        <form
          role="search"
          onSubmit={(event) => {
            event.preventDefault()
            goToResults()
          }}
        >
          <label className="relative flex items-center">
            <span className="sr-only">Search games</span>
            <SearchIcon className="pointer-events-none absolute left-3 size-[22px] text-white" />
            <input
              ref={inputRef}
              type="search"
              role="combobox"
              aria-expanded={showPanel}
              aria-controls={listboxId}
              aria-autocomplete="list"
              aria-activedescendant={showPanel && activeIndex >= 0 ? optionId(activeIndex) : undefined}
              autoComplete="off"
              spellCheck={false}
              placeholder="Search games"
              value={query}
              onChange={(event) => {
                setQuery(event.target.value)
                setIsOpen(true)
                setActiveIndex(-1)
              }}
              onFocus={() => setIsOpen(true)}
              onKeyDown={onKeyDown}
              className={cn(
                'h-[44px] w-full border bg-search pr-9 pl-9 text-base font-extrabold text-white shadow-input outline-none',
                'placeholder:font-semibold placeholder:text-muted [&::-webkit-search-cancel-button]:hidden',
                showPanel ? 'border-white' : 'border-transparent hover:bg-black/40 focus:border-white',
              )}
            />
            {query !== '' && (
              <button
                type="button"
                aria-label="Clear search"
                onClick={() => {
                  setQuery('')
                  inputRef.current?.focus()
                }}
                className="absolute right-2 grid size-6 place-items-center text-white hover:text-accent"
              >
                <svg aria-hidden viewBox="0 0 24 24" className="size-[20px]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                  <path d="M6 6l12 12M18 6 6 18" />
                </svg>
              </button>
            )}
          </label>
        </form>

        {showPanel && (
          <div className="absolute inset-x-0 top-full border-x border-b border-divider bg-page shadow-dropdown">
            {correction && (
              <p className="border-b border-divider px-3 py-2 text-sm text-soft" aria-live="polite">
                Showing results for <strong className="font-extrabold text-white">{correction}</strong>
              </p>
            )}

            <ul id={listboxId} role="listbox" aria-label="Search suggestions" className="max-h-[70vh] overflow-y-auto">
              {suggestions.isPending && games.length === 0 &&
                [0, 1, 2].map((row) => (
                  <li key={row} aria-hidden className="flex gap-3 border-b border-divider px-3 py-2">
                    <Skeleton className="h-[78px] w-[56px] shrink-0" />
                    <div className="flex flex-1 flex-col gap-2 pt-1">
                      <Skeleton className="h-[14px] w-3/4" />
                      <Skeleton className="h-[12px] w-1/3" />
                    </div>
                  </li>
                ))}

              {games.map((game, index) => (
                <li
                  key={game.slug}
                  id={optionId(index)}
                  role="option"
                  aria-selected={activeIndex === index}
                  onMouseEnter={() => setActiveIndex(index)}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => goToGame(game)}
                  className={cn(
                    'flex cursor-pointer items-center gap-3 border-b border-divider px-3 py-2',
                    activeIndex === index && 'bg-surface',
                  )}
                >
                  <img src={game.cover_image_url} alt="" className="h-[78px] w-[56px] shrink-0 object-cover" />
                  <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <div className="flex flex-wrap gap-1">
                      {game.platforms.map((platform) => (
                        <Badge key={platform} variant="outline">
                          {PLATFORM_LABELS[platform] ?? platform}
                        </Badge>
                      ))}
                    </div>
                    <p className="line-clamp-2 text-sm font-extrabold leading-snug text-white">{game.name}</p>
                  </div>
                  <PriceTag size="compact" price={game.price_from} className="shrink-0" />
                </li>
              ))}

              {!suggestions.isPending && games.length === 0 && (
                <li className="px-3 py-3 text-sm text-soft">No games match “{trimmed}”.</li>
              )}

              <li
                id={optionId(games.length)}
                role="option"
                aria-selected={activeIndex === games.length}
                onMouseEnter={() => setActiveIndex(games.length)}
                onMouseDown={(event) => event.preventDefault()}
                onClick={goToResults}
                className="p-3"
              >
                <span className={cn(buttonClasses({ variant: 'outline', fullWidth: true }), 'cursor-pointer', activeIndex === games.length && 'bg-white/10')}>
                  Show all results for “{trimmed}”
                </span>
              </li>
            </ul>
          </div>
        )}
      </div>
    </>
  )
}
