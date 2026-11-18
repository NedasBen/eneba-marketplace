import { useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react'
import { Link, useLocation, useNavigate } from 'react-router'
import { useAuth } from '../../lib/auth/useAuth'
import type { User } from '../../lib/api/auth'
import { cn } from '../../lib/cn'

/** Pages behind RequireAuth; logging out there goes home instead of to /login. */
const PROTECTED_PREFIXES = ['/orders', '/checkout']

/**
 * Signed-in header control: the user's name opens a menu with My orders and
 * Log out. Escape or a click outside closes it; focus returns to the button.
 */
export function UserMenu({ user, icon }: { user: User; icon: ReactNode }) {
  const { signOut } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [open, setOpen] = useState(false)
  const rootRef = useRef<HTMLDivElement>(null)
  const buttonRef = useRef<HTMLButtonElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)
  const menuId = useId()

  const close = (returnFocus: boolean) => {
    setOpen(false)
    if (returnFocus) buttonRef.current?.focus()
  }

  useEffect(() => {
    if (!open) return
    menuRef.current?.querySelector<HTMLElement>('[role="menuitem"]')?.focus()

    const onPointerDown = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('pointerdown', onPointerDown)
    return () => document.removeEventListener('pointerdown', onPointerDown)
  }, [open])

  function onMenuKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const items = Array.from(menuRef.current?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])
    const index = items.indexOf(document.activeElement as HTMLElement)

    if (event.key === 'Escape') {
      event.preventDefault()
      close(true)
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault()
      const step = event.key === 'ArrowDown' ? 1 : -1
      items[(index + step + items.length) % items.length]?.focus()
    } else if (event.key === 'Tab') {
      setOpen(false)
    }
  }

  async function logOut() {
    setOpen(false)
    if (PROTECTED_PREFIXES.some((prefix) => location.pathname.startsWith(prefix))) await navigate('/', { replace: true })
    await signOut()
  }

  const itemClasses = 'block w-full px-4 py-2 text-left text-sm font-semibold text-white hover:bg-surface focus:bg-surface focus:outline-none'

  return (
    <div ref={rootRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => setOpen((current) => !current)}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown') {
            event.preventDefault()
            setOpen(true)
          }
        }}
        className={cn('flex items-center gap-1 transition-colors hover:text-accent', open && 'text-accent')}
      >
        {icon}
        <span className="max-w-[140px] truncate">{user.name}</span>
        <svg aria-hidden viewBox="0 0 12 12" className={cn('size-[10px] transition-transform', open && 'rotate-180')} fill="currentColor">
          <path d="M1.5 4h9L6 9z" />
        </svg>
      </button>

      {open && (
        <div
          ref={menuRef}
          id={menuId}
          role="menu"
          aria-label="Account"
          onKeyDown={onMenuKeyDown}
          className="absolute top-full right-0 z-50 mt-2 min-w-[200px] bg-card py-1 shadow-dropdown"
        >
          <p className="truncate border-b border-white/10 px-4 py-2 text-xs text-muted">{user.email}</p>
          <Link to="/orders" role="menuitem" tabIndex={-1} onClick={() => setOpen(false)} className={itemClasses}>
            My orders
          </Link>
          <button type="button" role="menuitem" tabIndex={-1} onClick={() => void logOut()} className={itemClasses}>
            Log out
          </button>
        </div>
      )}
    </div>
  )
}
