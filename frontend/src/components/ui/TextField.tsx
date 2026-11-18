import { useId, type InputHTMLAttributes, type ReactNode } from 'react'
import { cn } from '../../lib/cn'

interface TextFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> {
  label: string
  /** Server or client validation message shown under the input. */
  error?: string
  /** Extra content under the input, e.g. a password rule checklist. */
  hint?: ReactNode
}

/**
 * Labelled input: dark inset field with a faint outline (so it reads on the
 * dark card), white border on focus, pink border and message when invalid.
 */
export function TextField({ label, error, hint, className, ...props }: TextFieldProps) {
  const id = useId()
  const errorId = `${id}-error`
  const hintId = `${id}-hint`
  const describedBy = [error ? errorId : null, hint ? hintId : null].filter(Boolean).join(' ') || undefined

  return (
    <div className={cn('flex flex-col gap-1', className)}>
      <label htmlFor={id} className="text-sm font-semibold text-soft">
        {label}
      </label>
      <input
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        className={cn(
          'h-[44px] w-full border bg-black/30 px-3 text-base font-semibold text-white shadow-input outline-none transition-colors',
          'placeholder:text-muted focus:border-white',
          error ? 'border-warning' : 'border-white/25 hover:border-white/50',
        )}
        {...props}
      />
      {error && (
        <p id={errorId} className="text-xs font-semibold text-warning">
          {error}
        </p>
      )}
      {hint && <div id={hintId}>{hint}</div>}
    </div>
  )
}
