import type { ReactNode } from 'react'
import { cn } from '../../lib/cn'

export type BadgeVariant = 'sale' | 'new' | 'highlight' | 'outline' | 'region' | 'region-restricted'

const variants: Record<BadgeVariant, string> = {
  // .IEzS80: red uppercase sale ribbon
  sale: 'bg-sale px-1 py-[3px] text-xs font-extrabold uppercase text-white',
  // .zKgp8v: small yellow label with dark purple text
  new: 'h-[17px] rounded-xs bg-accent px-1 text-[10px] font-extrabold leading-none text-badge-ink',
  // CASHBACK-style teal badge
  highlight: 'bg-highlight px-1 py-[3px] text-xs font-extrabold uppercase text-badge-ink',
  // "Digital good" pill in the search dropdown
  outline: 'rounded-pill border border-muted/70 px-[6px] py-[1px] text-[11px] font-semibold text-soft',
  // Region line on product cards (.Pm6lW1 / .firfXI): text only
  region: 'text-xs font-extrabold uppercase text-success',
  'region-restricted': 'text-xs font-extrabold uppercase text-warning',
}

export function Badge({ variant = 'outline', className, children }: { variant?: BadgeVariant; className?: string; children: ReactNode }) {
  return <span className={cn('inline-flex w-fit items-center whitespace-nowrap', variants[variant], className)}>{children}</span>
}
