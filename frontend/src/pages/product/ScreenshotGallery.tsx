import { useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { screenshotThumbnailProps } from '../../lib/steamImages'

const MAX_THUMBNAILS = 6

/**
 * Screenshot strip (as on eneba.com) that opens a lightbox on the native
 * modal <dialog>: focus is trapped and restored by the browser, Escape
 * closes, ←/→ step through the images.
 */
export function ScreenshotGallery({ screenshots, gameName }: { screenshots: string[]; gameName: string }) {
  const dialogRef = useRef<HTMLDialogElement>(null)
  const [openIndex, setOpenIndex] = useState<number | null>(null)
  const shots = screenshots.slice(0, MAX_THUMBNAILS)

  useEffect(() => {
    const dialog = dialogRef.current
    if (dialog === null) return
    if (openIndex !== null && !dialog.open) dialog.showModal()
    if (openIndex === null && dialog.open) dialog.close()
  }, [openIndex])

  if (shots.length === 0) return null

  const step = (delta: number) => setOpenIndex((index) => (index === null ? null : (index + delta + shots.length) % shots.length))

  const onKeyDown = (event: KeyboardEvent<HTMLDialogElement>) => {
    if (event.key === 'ArrowRight') step(1)
    if (event.key === 'ArrowLeft') step(-1)
  }

  return (
    <section aria-label="Screenshots">
      <ul className="grid grid-cols-3 gap-2 sm:grid-cols-6">
        {shots.map((url, index) => (
          <li key={url}>
            <button
              type="button"
              onClick={() => setOpenIndex(index)}
              aria-label={`Open screenshot ${index + 1} of ${shots.length}`}
              className="group block aspect-video w-full overflow-hidden bg-card"
            >
              <img
                {...screenshotThumbnailProps(url)}
                sizes="(min-width: 600px) 125px, 33vw"
                alt=""
                width={600}
                height={338}
                loading="lazy"
                decoding="async"
                className="size-full object-cover transition-opacity group-hover:opacity-80"
              />
            </button>
          </li>
        ))}
      </ul>

      <dialog
        ref={dialogRef}
        aria-label={`${gameName} screenshots`}
        onClose={() => setOpenIndex(null)}
        onKeyDown={onKeyDown}
        onClick={(event) => {
          if (event.target === event.currentTarget) setOpenIndex(null)
        }}
        className="m-auto max-h-none max-w-[min(1200px,94vw)] bg-transparent p-0 text-white backdrop:bg-black/85"
      >
        {openIndex !== null && (
          <figure className="relative">
            <img src={shots[openIndex]} alt={`${gameName} screenshot ${openIndex + 1} of ${shots.length}`} className="max-h-[82vh] w-full object-contain" />
            <figcaption className="mt-2 flex items-center justify-between gap-3 text-sm text-soft">
              <span>
                {openIndex + 1} / {shots.length}
              </span>
              <span className="flex gap-2">
                <button type="button" onClick={() => step(-1)} className="bg-white/10 px-3 py-1 font-extrabold hover:bg-white/30" aria-label="Previous screenshot">
                  ‹
                </button>
                <button type="button" onClick={() => step(1)} className="bg-white/10 px-3 py-1 font-extrabold hover:bg-white/30" aria-label="Next screenshot">
                  ›
                </button>
                <button type="button" onClick={() => setOpenIndex(null)} className="bg-white/10 px-3 py-1 font-extrabold hover:bg-white/30">
                  Close
                </button>
              </span>
            </figcaption>
          </figure>
        )}
      </dialog>
    </section>
  )
}
