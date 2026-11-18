import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Navigate, useParams } from 'react-router'
import { Container } from '../../components/layout/Container'
import { ButtonLink } from '../../components/ui/Button'
import { EmptyState, ErrorState } from '../../components/ui/PageState'
import { Skeleton } from '../../components/ui/Skeleton'
import { CART_QUERY_KEY } from '../../lib/api/cart'
import { ApiError } from '../../lib/api/client'
import { orderQueries, ordersApi, type CardInput, type Order } from '../../lib/api/orders'
import { useAuth } from '../../lib/auth/useAuth'
import { cn } from '../../lib/cn'
import { clockOffsetMs, formatCountdown, useCountdown } from '../../lib/countdown'
import { formatMoney } from '../../lib/format'
import { FormAlert } from '../auth/AuthCard'
import { OrderSummary } from '../orders/OrderSummary'
import { CardForm } from './CardForm'

function ExpiredNotice() {
  return (
    <EmptyState title="Your reservation expired" action={<ButtonLink to="/cart">Back to cart</ButtonLink>}>
      The keys were held for 15 minutes and have gone back on sale. Your cart still has the items: check out again to reserve them.
    </EmptyState>
  )
}

/** "12:34 left to pay", turning urgent in the last two minutes. */
function Countdown({ seconds }: { seconds: number }) {
  return (
    <p role="timer" aria-live="off" className={cn('flex items-center gap-2 text-sm font-semibold', seconds <= 120 ? 'text-warning' : 'text-soft')}>
      Keys reserved for <span className="font-mono text-lg font-extrabold text-white tabular-nums">{formatCountdown(seconds)}</span>
    </p>
  )
}

function PaymentPanel({ order, offsetMs, onPaid }: { order: Order; offsetMs: number; onPaid: (paid: Order) => void }) {
  const { user } = useAuth()
  const seconds = useCountdown(order.expires_at, offsetMs) ?? 0
  const [submitting, setSubmitting] = useState(false)
  const [decline, setDecline] = useState<string | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [expired, setExpired] = useState(false)

  if (expired || seconds === 0) return <ExpiredNotice />

  const pay = async (card: CardInput) => {
    setSubmitting(true)
    setDecline(null)
    setError(null)

    try {
      onPaid(await ordersApi.pay(order.reference, card))
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 402) setDecline(caught.message)
      else if (caught instanceof ApiError && caught.status === 410) setExpired(true)
      else setError(caught instanceof ApiError ? caught : new ApiError(0, 'Something went wrong. Please try again.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <section aria-labelledby="payment-title" className="flex flex-col gap-4 self-start bg-card p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 id="payment-title" className="text-lg font-extrabold">
          Pay {formatMoney(order.total)}
        </h2>
        <Countdown seconds={seconds} />
      </div>
      {decline && (
        <FormAlert>
          {decline} <span className="font-normal text-soft">Your keys are still reserved: you can try again.</span>
        </FormAlert>
      )}
      {error && error.status !== 422 && <FormAlert>{error.isNetworkError ? 'Could not reach the server. Try again.' : error.message}</FormAlert>}
      <CardForm submitting={submitting} serverErrors={error?.status === 422 ? error.errors : {}} defaultName={user?.name ?? ''} onSubmit={(card) => void pay(card)} />
    </section>
  )
}

/**
 * Payment for a pending order: summary, reservation countdown and the
 * simulated card form. A paid order goes to its keys page; an expired one
 * shows how to start again.
 */
export function CheckoutPage() {
  const { reference = '' } = useParams()
  const queryClient = useQueryClient()
  const order = useQuery(orderQueries.detail(reference))
  const [justPaid, setJustPaid] = useState(false)
  // dataUpdatedAt is when this copy of the order arrived, on the device clock.
  const offsetMs = useMemo(() => (order.data ? clockOffsetMs(order.data.server_time, order.dataUpdatedAt) : 0), [order.data, order.dataUpdatedAt])

  // Paid here or earlier: the keys page. One redirect for both, so the
  // success state can't be lost to a second navigation.
  if (order.data?.status === 'paid') return <Navigate to={`/orders/${reference}`} replace state={justPaid ? { justPaid: true } : undefined} />

  const onPaid = (paid: Order) => {
    setJustPaid(true)
    queryClient.setQueryData(orderQueries.detail(reference).queryKey, paid)
    // The bought lines left the server cart.
    void queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY })
  }

  return (
    <Container className="flex flex-col gap-5 py-6">
      <title>Checkout | Eneba Marketplace</title>
      <h1 className="text-2xl font-extrabold">Checkout</h1>

      {order.isPending ? (
        <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_400px]">
          <Skeleton className="h-[200px] w-full" />
          <Skeleton className="h-[420px] w-full" />
        </div>
      ) : order.isError ? (
        order.error instanceof ApiError && order.error.status === 404 ? (
          <EmptyState title="Order not found" action={<ButtonLink to="/orders">My orders</ButtonLink>}>
            This order doesn’t exist or belongs to another account.
          </EmptyState>
        ) : (
          <ErrorState
            title="We couldn’t load this order"
            error={order.error}
            onRetry={() => void order.refetch()}
            actions={
              <ButtonLink to="/cart" variant="secondary">
                Back to cart
              </ButtonLink>
            }
          />
        )
      ) : order.data.status === 'expired' ? (
        <ExpiredNotice />
      ) : (
        <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_400px]">
          <OrderSummary order={order.data} />
          <PaymentPanel order={order.data} offsetMs={offsetMs} onPaid={onPaid} />
        </div>
      )}
    </Container>
  )
}
