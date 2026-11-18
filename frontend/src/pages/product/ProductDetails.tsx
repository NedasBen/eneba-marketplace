import { useId, useState } from 'react'
import { Link } from 'react-router'
import type { ProductDetail } from '../../lib/api/types'
import { cn } from '../../lib/cn'

const COLLAPSED_LENGTH = 700

const formatDate = (date: string) => new Intl.DateTimeFormat('en-IE', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${date}T00:00:00`))

/** Genres, the plain-text description ("Read more") and the details table. */
export function ProductDetails({ product }: { product: ProductDetail }) {
  const { game } = product
  const [expanded, setExpanded] = useState(false)
  const descriptionId = useId()
  const isLong = game.description.length > COLLAPSED_LENGTH
  const paragraphs = game.description.split(/\n{2,}/).filter((paragraph) => paragraph.trim() !== '')

  const details: [string, string | number][] = [
    ['Platform', `${product.platform.name} (activate on ${product.platform.activation_service})`],
    ['Region', product.region.name],
    ...(game.released_at ? [['Release date', formatDate(game.released_at)] as [string, string]] : []),
    ...(game.developer ? [['Developer', game.developer] as [string, string]] : []),
    ...(game.publisher ? [['Publisher', game.publisher] as [string, string]] : []),
    ...(game.metacritic ? [['Metacritic', game.metacritic] as [string, number]] : []),
  ]

  return (
    <div className="flex flex-col gap-6">
      <section aria-labelledby={`${descriptionId}-heading`} className="flex flex-col gap-3">
        <h2 id={`${descriptionId}-heading`} className="text-lg font-extrabold">
          Product details
        </h2>
        {game.genres.length > 0 && (
          <ul aria-label="Genres" className="flex flex-wrap gap-2">
            {game.genres.map((genre) => (
              <li key={genre.slug}>
                <Link to={`/store?genre=${genre.slug}`} className="inline-flex rounded-pill bg-band px-2 py-1 text-xs font-semibold text-soft hover:text-white">
                  {genre.name}
                </Link>
              </li>
            ))}
          </ul>
        )}

        <div className="bg-band p-4">
          <h3 className="mb-3 text-base font-extrabold">{game.name}</h3>
          <div id={descriptionId} className={cn('relative flex flex-col gap-3 overflow-hidden text-sm leading-relaxed text-soft', isLong && !expanded && 'max-h-[220px]')}>
            {paragraphs.map((paragraph, index) => (
              <p key={index} className="whitespace-pre-line">
                {paragraph}
              </p>
            ))}
            {isLong && !expanded && <div aria-hidden className="absolute inset-x-0 bottom-0 h-16 bg-linear-to-t from-band to-transparent" />}
          </div>
          {isLong && (
            <button
              type="button"
              aria-expanded={expanded}
              aria-controls={descriptionId}
              onClick={() => setExpanded((value) => !value)}
              className="mx-auto mt-3 block min-h-6 px-2 text-sm font-semibold text-accent hover:text-accent-hover"
            >
              {expanded ? 'Show less' : 'Read more'}
            </button>
          )}
        </div>
      </section>

      <section aria-labelledby={`${descriptionId}-other`} className="flex flex-col gap-3">
        <h2 id={`${descriptionId}-other`} className="text-lg font-extrabold">
          Other details
        </h2>
        <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2 bg-band p-4 text-sm">
          {details.map(([label, value]) => (
            <div key={label} className="contents">
              <dt className="text-right text-muted">{label}</dt>
              <dd className="text-white">{value}</dd>
            </div>
          ))}
        </dl>
      </section>
    </div>
  )
}
