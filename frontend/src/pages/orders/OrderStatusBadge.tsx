import type { OrderStatus } from '../../lib/api/orders'
import { cn } from '../../lib/cn'

const styles: Record<OrderStatus, { label: string; className: string }> = {
  paid: { label: 'Paid', className: 'bg-success text-badge-ink' },
  pending: { label: 'Awaiting payment', className: 'bg-accent text-badge-ink' },
  expired: { label: 'Expired', className: 'bg-white/15 text-soft' },
}

/** Order status pill: Paid (green), Awaiting payment (yellow), Expired (grey). */
export function OrderStatusBadge({ status, className }: { status: OrderStatus; className?: string }) {
  const style = styles[status]

  return <span className={cn('inline-flex w-fit items-center whitespace-nowrap rounded-xs px-2 py-[3px] text-xs font-extrabold uppercase', style.className, className)}>{style.label}</span>
}
