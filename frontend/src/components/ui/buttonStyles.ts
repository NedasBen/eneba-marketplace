import { cn } from '../../lib/cn'

export type ButtonVariant = 'primary' | 'secondary' | 'purple' | 'outline' | 'ghost'
export type ButtonSize = 'md' | 'sm'

const base =
  'inline-flex items-center justify-center gap-1 text-center select-none transition-colors duration-150 ease-in-out ' +
  'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50'

/** Sizes from `.qzNRBf…` (35px, 800 15px) and `.GE4aMb` (28px, 600 12px). */
const sizes: Record<ButtonSize, string> = {
  md: 'min-h-7 px-2 text-btn font-extrabold',
  sm: 'min-h-[28px] px-[6px] text-xs font-semibold',
}

const variants: Record<ButtonVariant, string> = {
  // .pr0yIU: yellow CTA, black text
  primary: 'bg-accent text-black hover:bg-accent-hover focus-visible:bg-accent-hover active:bg-accent-active',
  // .GE4aMb: translucent white
  secondary: 'border border-white/10 bg-white/10 text-white/85 hover:border-white/40 hover:bg-white/40 hover:text-white',
  // .UCOPAE: brighter purple
  purple: 'bg-raised text-white hover:bg-surface active:bg-surface',
  // "Show all results" in the search dropdown
  outline: 'border border-white text-white hover:bg-white/10',
  ghost: 'text-white hover:text-accent',
}

/** Button look for any element (buttons, router links, list options). */
export function buttonClasses({ variant = 'primary', size = 'md', fullWidth = false }: { variant?: ButtonVariant; size?: ButtonSize; fullWidth?: boolean } = {}): string {
  return cn(base, sizes[size], variants[variant], fullWidth && 'w-full')
}
