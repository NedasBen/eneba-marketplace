import type { ReactNode } from 'react'
import { cn } from '../../lib/cn'

/** The 1200px page column. Pages opt in, so sections can still span full width. */
export function Container({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn('mx-auto w-full max-w-page px-4', className)}>{children}</div>
}
