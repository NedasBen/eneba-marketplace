import type { InputHTMLAttributes, ReactNode } from 'react'
import { cn } from '../../lib/cn'

interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: ReactNode
  /** Right-aligned extra, e.g. a result count. */
  meta?: ReactNode
}

/**
 * Eneba's filter checkbox (`.radioLabel .checkbox`): 20px square, 2px #dcd8e6
 * border, filled yellow with a dark tick when checked. Disabled options are
 * greyed out but stay in place so the list doesn't jump.
 */
export function Checkbox({ label, meta, className, disabled, ...props }: CheckboxProps) {
  return (
    <label className={cn('group flex min-h-7 items-center gap-2 text-sm', disabled ? 'cursor-not-allowed opacity-40' : 'cursor-pointer', className)}>
      <input type="checkbox" disabled={disabled} className="peer sr-only" {...props} />
      <span
        aria-hidden
        className={cn(
          'grid size-[20px] shrink-0 place-items-center border-2 border-soft transition-colors duration-150',
          'peer-checked:border-accent peer-checked:bg-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent',
          '[&>svg]:invisible peer-checked:[&>svg]:visible',
          !disabled && 'group-hover:border-white',
        )}
      >
        <svg viewBox="0 0 16 16" className="size-[12px] text-badge-ink" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round">
          <path d="m3 8.5 3 3 7-7" />
        </svg>
      </span>
      <span className="min-w-0 flex-1 truncate font-semibold text-white">{label}</span>
      {meta !== undefined && <span className="shrink-0 text-xs text-muted">{meta}</span>}
    </label>
  )
}
