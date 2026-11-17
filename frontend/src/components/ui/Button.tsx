import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Link, type LinkProps } from 'react-router'
import { cn } from '../../lib/cn'
import { buttonClasses, type ButtonSize, type ButtonVariant } from './buttonStyles'

function Spinner() {
  return <span aria-hidden className="size-[14px] animate-spin rounded-full border-2 border-current border-r-transparent" />
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  size?: ButtonSize
  fullWidth?: boolean
  loading?: boolean
  children: ReactNode
}

export function Button({ variant, size, fullWidth, loading = false, disabled, className, children, type = 'button', ...props }: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cn(buttonClasses({ variant, size, fullWidth }), className)}
      {...props}
    >
      {loading && <Spinner />}
      {children}
    </button>
  )
}

interface ButtonLinkProps extends LinkProps {
  variant?: ButtonVariant
  size?: ButtonSize
  fullWidth?: boolean
}

/** A router link that looks like a Button. */
export function ButtonLink({ variant, size, fullWidth, className, ...props }: ButtonLinkProps) {
  return <Link className={cn(buttonClasses({ variant, size, fullWidth }), className)} {...props} />
}
