import { useEffect, useRef, type ReactNode } from 'react'
import { Button } from '../../components/ui/Button'

interface FilterDrawerProps {
  open: boolean
  onClose: () => void
  resultCount: number | undefined
  children: ReactNode
}

/**
 * Mobile filter drawer built on the native modal <dialog>: the browser traps
 * focus, makes the page behind inert, closes on Escape and restores focus to
 * the "Filters" button afterwards. Filters apply live; the footer shows the
 * live result count.
 */
export function FilterDrawer({ open, onClose, resultCount, children }: FilterDrawerProps) {
  const dialogRef = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = dialogRef.current
    if (dialog === null) return

    if (open && !dialog.open) {
      dialog.showModal()
      document.documentElement.style.overflow = 'hidden'
    } else if (!open && dialog.open) {
      dialog.close()
    }

    return () => {
      document.documentElement.style.overflow = ''
    }
  }, [open])

  return (
    <dialog
      ref={dialogRef}
      aria-labelledby="filter-drawer-title"
      onClose={onClose}
      onClick={(event) => {
        // A click on the backdrop lands on the <dialog> element itself.
        if (event.target === event.currentTarget) onClose()
      }}
      className="m-0 h-dvh max-h-dvh w-[min(360px,88vw)] max-w-none bg-page p-0 text-white backdrop:bg-black/55"
    >
      <div className="flex h-full flex-col">
        <div className="flex items-center justify-between border-b border-white/10 px-3 py-3">
          <h2 id="filter-drawer-title" className="text-lg font-extrabold">
            Filters
          </h2>
          <button type="button" onClick={onClose} aria-label="Close filters" className="grid size-7 place-items-center hover:text-accent">
            <svg aria-hidden viewBox="0 0 24 24" className="size-[22px]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
              <path d="M6 6l12 12M18 6 6 18" />
            </svg>
          </button>
        </div>
        <div className="flex-1 overflow-y-auto">{children}</div>
        <div className="border-t border-white/10 p-3">
          <Button fullWidth onClick={onClose}>
            {resultCount === undefined ? 'Show results' : `Show ${resultCount} ${resultCount === 1 ? 'result' : 'results'}`}
          </Button>
        </div>
      </div>
    </dialog>
  )
}
