import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router'
import { Container } from '../../components/layout/Container'
import { ButtonLink } from '../../components/ui/Button'
import { EmptyState, ErrorState } from '../../components/ui/PageState'
import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { orderQueries, type OrderSummary } from '../../lib/api/orders'
import { formatMoney } from '../../lib/format'
import { coverImageProps } from '../../lib/steamImages'
import { OrderStatusBadge } from './OrderStatusBadge'

const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' })

function OrderRow({ order }: { order: OrderSummary }) {
  const pending = order.status === 'pending'
  const href = pending ? `/checkout/${order.reference}` : `/orders/${order.reference}`
  const products = order.product_names.join(', ') + (order.more_products > 0 ? ` and ${order.more_products} more` : '')

  return (
    <li className="relative flex gap-3 bg-band p-3 transition-colors focus-within:bg-surface hover:bg-surface sm:items-center sm:p-4">
      {order.cover_image_url ? (
        <img {...coverImageProps(order.cover_image_url)} sizes="56px" alt="" width={56} height={78} loading="lazy" decoding="async" className="aspect-[1/1.4] w-[56px] shrink-0 object-cover" />
      ) : (
        <div aria-hidden className="aspect-[1/1.4] w-[56px] shrink-0 bg-card" />
      )}

      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <div className="flex items-start justify-between gap-3">
          <OrderStatusBadge status={order.status} />
          <p className="font-extrabold">{formatMoney(order.total)}</p>
        </div>
        <time dateTime={order.created_at} className="text-sm font-semibold text-soft">
          {dateFormat.format(new Date(order.created_at))}
        </time>
        {/* The whole row is clickable through this link's overlay. */}
        <Link to={href} className="line-clamp-2 font-extrabold after:absolute after:inset-0 hover:text-accent">
          {products || 'Order'}
        </Link>
        <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs">
          <p className="min-w-0 text-muted">
            Order <span className="font-mono break-all">{order.reference}</span> · {order.item_count} {order.item_count === 1 ? 'key' : 'keys'}
          </p>
          <span aria-hidden className={pending ? 'font-extrabold text-accent' : 'font-semibold text-soft'}>
            {pending ? 'Continue to payment ›' : 'View ›'}
          </span>
        </div>
      </div>
    </li>
  )
}

/** Order history: newest first, 10 per page, page number in the URL. */
export function OrdersPage() {
  const [searchParams] = useSearchParams()
  const page = Math.max(1, Number.parseInt(searchParams.get('page') ?? '1', 10) || 1)
  const orders = useQuery({ ...orderQueries.list(page), placeholderData: keepPreviousData })

  return (
    <Container className="flex flex-col gap-5 py-6">
      <title>{page > 1 ? `My orders, page ${page} | Eneba Marketplace` : 'My orders | Eneba Marketplace'}</title>
      <h1 className="text-2xl font-extrabold">My orders</h1>

      {orders.isPending ? (
        <ul className="flex flex-col gap-2" aria-busy aria-label="Loading orders">
          {Array.from({ length: 4 }, (_, index) => (
            <li key={index}>
              <Skeleton className="h-[102px] w-full" />
            </li>
          ))}
        </ul>
      ) : orders.isError ? (
        <ErrorState title="We couldn’t load your orders" error={orders.error} onRetry={() => void orders.refetch()} />
      ) : orders.data.data.length === 0 ? (
        page > 1 ? (
          <EmptyState title="No orders on this page" action={<ButtonLink to="/orders">Go to the first page</ButtonLink>} />
        ) : (
          <EmptyState title="No orders yet" action={<ButtonLink to="/store">Browse the store</ButtonLink>}>
            Keys you buy show up here, ready to copy whenever you need them.
          </EmptyState>
        )
      ) : (
        <>
          <p className="text-sm text-muted" aria-live="polite">
            {orders.data.meta.total} {orders.data.meta.total === 1 ? 'order' : 'orders'}
          </p>
          <ul className="flex flex-col gap-2" aria-busy={orders.isPlaceholderData || undefined}>
            {orders.data.data.map((order) => (
              <OrderRow key={order.reference} order={order} />
            ))}
          </ul>
          <Pagination page={orders.data.meta.current_page} lastPage={orders.data.meta.last_page} hrefFor={(target) => (target === 1 ? '/orders' : `/orders?page=${target}`)} />
        </>
      )}
    </Container>
  )
}
