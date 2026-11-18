import type { ReactNode } from 'react'
import { cn } from '../../lib/cn'
import { describeError } from '../../lib/errors'
import { Button } from './Button'

type HeadingLevel = 'h1' | 'h2'

/**
 * The one error state for pages and sections: a title, what happened, and
 * "Try again" (plus optional extra actions such as "Back to cart").
 */
export function ErrorState({
  title,
  error,
  onRetry,
  actions,
  as: Heading = 'h2',
  className,
}: {
  title: string
  error?: unknown
  onRetry?: () => void
  actions?: ReactNode
  as?: HeadingLevel
  className?: string
}) {
  return (
    <div role="alert" className={cn('flex flex-col items-start gap-3 bg-black/20 p-5', className)}>
      <Heading className="text-lg font-extrabold">{title}</Heading>
      <p className="max-w-[60ch] text-sm text-soft">{describeError(error)}</p>
      {(onRetry || actions) && (
        <div className="flex flex-wrap gap-2">
          {onRetry && <Button onClick={onRetry}>Try again</Button>}
          {actions}
        </div>
      )}
    </div>
  )
}

/** The one empty / dead-end state: a title, a short explanation and what to do next. */
export function EmptyState({
  title,
  children,
  action,
  as: Heading = 'h2',
  className,
}: {
  title: string
  children?: ReactNode
  action?: ReactNode
  as?: HeadingLevel
  className?: string
}) {
  return (
    <div className={cn('flex flex-col items-start gap-3 bg-band p-5', className)}>
      <Heading className="text-lg font-extrabold">{title}</Heading>
      {children && <div className="max-w-[60ch] text-sm text-soft">{children}</div>}
      {action}
    </div>
  )
}
